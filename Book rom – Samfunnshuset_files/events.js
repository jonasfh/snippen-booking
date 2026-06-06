(()=>{const __name=(target)=>target;((w, version2, schemaVersions, maxEventsPerBatch = 50, maxKeepaliveBatchBytes = 50 * 1024, maxReplayBodyBytes = 512 * 1024, maxReplayEventsPerChunk = 100, maxReplayTotalBytes = 5 * 1024 * 1024, maxReplayChunks = 50, maxReplayDurationMs = 10 * 60 * 1e3, replayFlushIntervalMs = 2e3) => {
  const existing = w.__lovableEvents || {};
  if (existing.__userAppEventsSdkVersion) return;
  const d = w.document;
  const nav = w.navigator || {};
  const perf = w.performance || { now: /* @__PURE__ */ __name(() => Date.now(), "now") };
  const loc = w.location || { pathname: "", href: "" };
  const isInternalPath = /* @__PURE__ */ __name((path) => path === "/__codex_token_check" || path.startsWith("/__l5e/"), "isInternalPath");
  if (isInternalPath(loc.pathname)) return;
  const script = d && d.currentScript;
  const artifact = script ? {
    kind: script.getAttribute("data-artifact-kind") || null,
    id: script.getAttribute("data-artifact-id") || null,
    commit_sha: script.getAttribute("data-commit-sha") || null,
    context_token: script.getAttribute("data-context-token") || null
  } : {};
  const replayMode = script?.getAttribute("data-replay") || null;
  const trackUrl = script?.getAttribute("data-track-url") || "/__l5e/trackevents";
  const replayUrl = script?.getAttribute("data-replay-url") || "/__l5e/replay";
  const replayScriptUrl = script?.getAttribute("data-replay-script-url") || "/__l5e/rrweb-record.js";
  const parseSampleRate = /* @__PURE__ */ __name((value) => {
    const rate = typeof value === "string" ? Number(value) : 0;
    if (!Number.isFinite(rate) || rate <= 0) return 0;
    return rate >= 1 ? 1 : rate;
  }, "parseSampleRate");
  const replaySampleRate = parseSampleRate(script?.getAttribute("data-replay-sample-rate") || null);
  const randomId = /* @__PURE__ */ __name(() => w.crypto && typeof w.crypto.randomUUID === "function" ? w.crypto.randomUUID() : String(Date.now()) + "-" + Math.random().toString(16).slice(2), "randomId");
  const storageGetOrCreate = /* @__PURE__ */ __name((storage, key) => {
    try {
      if (!storage) return { value: randomId(), created: true };
      let value = storage.getItem(key);
      if (!value) {
        value = randomId();
        storage.setItem(key, value);
        return { value, created: true };
      }
      return { value, created: false };
    } catch {
      return { value: randomId(), created: true };
    }
  }, "storageGetOrCreate");
  const rollingSessionTimeoutMs = 30 * 60 * 1e3;
  const rollingSessionStorageKey = "__lovable_session";
  const readRollingSession = /* @__PURE__ */ __name((storage, key) => {
    try {
      const raw2 = storage?.getItem(key);
      if (!raw2) return null;
      const parsed = JSON.parse(raw2);
      const record = parsed && typeof parsed === "object" && !Array.isArray(parsed) ? parsed : null;
      const value = record?.value;
      const expiresAt = record?.expires_at;
      if (typeof value !== "string" || !value || typeof expiresAt !== "number" || !Number.isFinite(expiresAt)) {
        return null;
      }
      return { value, expiresAt };
    } catch {
      return null;
    }
  }, "readRollingSession");
  const writeRollingSession = /* @__PURE__ */ __name((storage, key, value, expiresAt) => {
    try {
      storage?.setItem(key, JSON.stringify({ value, expires_at: expiresAt }));
    } catch {
    }
  }, "writeRollingSession");
  const loadRollingSession = /* @__PURE__ */ __name((storage, key) => {
    const now = Date.now();
    const expiresAt = now + rollingSessionTimeoutMs;
    const existingSession = readRollingSession(storage, key);
    if (existingSession && existingSession.expiresAt > now) {
      writeRollingSession(storage, key, existingSession.value, expiresAt);
      return { value: existingSession.value, expiresAt, created: false };
    }
    const value = randomId();
    writeRollingSession(storage, key, value, expiresAt);
    return { value, expiresAt, created: true };
  }, "loadRollingSession");
  const anonymousIdentity = storageGetOrCreate(w.localStorage, "__lovable_anonymous_id");
  const sessionIdentity = loadRollingSession(w.localStorage, rollingSessionStorageKey);
  const anonymousId = anonymousIdentity.value;
  let sessionId = sessionIdentity.value;
  let sessionExpiresAt = sessionIdentity.expiresAt;
  let appUserId = null;
  let pageViewId = randomId();
  const unitIntervalFor = /* @__PURE__ */ __name((value) => {
    let hash = 2166136261;
    for (let index = 0; index < value.length; index++) {
      hash ^= value.charCodeAt(index);
      hash = Math.imul(hash, 16777619);
    }
    return (hash >>> 0) / 4294967296;
  }, "unitIntervalFor");
  const isReplaySampledIn = replaySampleRate > 0 && unitIntervalFor(sessionId) < replaySampleRate;
  let replayId = null;
  let replayEventCount = 0;
  let replayStartedAt = 0;
  let replayFinalized = false;
  let stopReplayRecorder = null;
  let replayChunkSequence = 0;
  let replayUploadedBytes = 0;
  let replayFlushTimer = null;
  let replayDurationTimer = null;
  let replayRecorderLoading = false;
  let replayHasFullSnapshot = false;
  const replayQueue = [];
  const replayMaskTextSelector = [
    "[data-lovable-mask]",
    "[data-sensitive]",
    "[data-private]",
    "[data-auth]",
    "[data-payment]",
    "[contenteditable]",
    "[autocomplete]",
    "[name*='email' i]",
    "[name*='phone' i]",
    "[name*='address' i]",
    "[name*='token' i]",
    "[name*='secret' i]"
  ].join(",");
  const replayBlockSelector = [
    "canvas",
    "embed",
    "iframe",
    "object",
    "video",
    "[data-lovable-block]",
    ".rr-block",
    "[data-payment]",
    "[data-auth]",
    "[data-private]"
  ].join(",");
  const replayIgnoreSelector = "[data-lovable-ignore],.rrweb-ignore";
  const maskReplayText = /* @__PURE__ */ __name((text) => text.replace(/[^\s]/gu, "*"), "maskReplayText");
  const queue = [];
  let flushTimer = null;
  const batchSize = Math.max(1, maxEventsPerBatch);
  const projectEventNamePattern = /^project\.[a-z][a-z0-9_]{0,63}(\.[a-z][a-z0-9_]{0,63})*$/u;
  const schemaVersionFor = /* @__PURE__ */ __name((eventName) => {
    const schemaVersion = schemaVersions[eventName];
    if (typeof schemaVersion === "number" && schemaVersion > 0) return schemaVersion;
    return projectEventNamePattern.test(eventName) ? 1 : null;
  }, "schemaVersionFor");
  const byteLength = /* @__PURE__ */ __name((value) => {
    const TextEncoderCtor = typeof TextEncoder === "function" ? TextEncoder : null;
    return TextEncoderCtor ? new TextEncoderCtor().encode(value).length : value.length;
  }, "byteLength");
  const createBatchBody = /* @__PURE__ */ __name((events) => JSON.stringify({
    envelope_schema_version: 1,
    batch_id: randomId(),
    sent_at: (/* @__PURE__ */ new Date()).toISOString(),
    sdk: { name: "lovable-user-app-events", version: version2 },
    artifact,
    events
  }), "createBatchBody");
  const transmitBatchBody = /* @__PURE__ */ __name((body) => {
    const BlobCtor = w.Blob || (typeof Blob === "function" ? Blob : null);
    if (nav.sendBeacon && BlobCtor) {
      const ok = nav.sendBeacon(trackUrl, new BlobCtor([body], { type: "application/json" }));
      if (ok) return;
    }
    const fetchFn = w.fetch || (typeof fetch === "function" ? fetch : null);
    if (!fetchFn) return;
    fetchFn(trackUrl, {
      method: "POST",
      headers: { "content-type": "application/json" },
      body,
      keepalive: true
    }).catch(() => {
    });
  }, "transmitBatchBody");
  const sendBatch = /* @__PURE__ */ __name((events) => {
    let start = 0;
    while (start < events.length) {
      let end = events.length;
      let body = createBatchBody(events.slice(start, end));
      while (end - start > 1 && byteLength(body) > maxKeepaliveBatchBytes) {
        end = start + Math.max(1, Math.floor((end - start) / 2));
        body = createBatchBody(events.slice(start, end));
      }
      transmitBatchBody(body);
      start = end;
    }
  }, "sendBatch");
  const createReplayChunkBody = /* @__PURE__ */ __name((events, chunkID, chunkSequence) => {
    if (!replayId) return null;
    return JSON.stringify({
      envelope_schema_version: 1,
      replay_schema_version: 1,
      replay_id: replayId,
      chunk_id: chunkID,
      chunk_sequence: chunkSequence,
      sent_at: (/* @__PURE__ */ new Date()).toISOString(),
      sdk: { name: "lovable-user-app-events", version: version2 },
      artifact,
      anonymous_id: anonymousId,
      session_id: sessionId,
      page_view_id: pageViewId,
      events
    });
  }, "createReplayChunkBody");
  const canSendReplayEvent = /* @__PURE__ */ __name((event) => {
    const body = createReplayChunkBody([event], "00000000-0000-4000-8000-000000000000", replayChunkSequence);
    return !!body && byteLength(body) <= maxReplayBodyBytes;
  }, "canSendReplayEvent");
  const replayEventType = /* @__PURE__ */ __name((event) => {
    const record = event && typeof event === "object" ? event : null;
    return typeof record?.type === "number" ? record.type : null;
  }, "replayEventType");
  const transmitReplayChunkBody = /* @__PURE__ */ __name((body, preferKeepalive = false) => {
    const fetchFn = w.fetch || (typeof fetch === "function" ? fetch : null);
    if (!fetchFn) return;
    const bodyBytes = byteLength(body);
    const useKeepalive = preferKeepalive && bodyBytes <= maxKeepaliveBatchBytes;
    fetchFn(replayUrl, {
      method: "POST",
      headers: { "content-type": "application/json" },
      body,
      ...useKeepalive ? { keepalive: true } : {}
    }).then((response) => {
      if (!response.ok) {
        stopReplayRecording("upload_failed", true);
      }
    }).catch(() => {
      stopReplayRecording("upload_failed", true);
    });
  }, "transmitReplayChunkBody");
  const replayDurationMs = /* @__PURE__ */ __name(() => Math.max(0, perf.now() - replayStartedAt), "replayDurationMs");
  const clearReplayFlushTimer = /* @__PURE__ */ __name(() => {
    if (!replayFlushTimer) return;
    const clearTimer = w.clearTimeout || (typeof clearTimeout === "function" ? clearTimeout : null);
    if (clearTimer) clearTimer(replayFlushTimer);
    replayFlushTimer = null;
  }, "clearReplayFlushTimer");
  const clearReplayDurationTimer = /* @__PURE__ */ __name(() => {
    if (!replayDurationTimer) return;
    const clearTimer = w.clearTimeout || (typeof clearTimeout === "function" ? clearTimeout : null);
    if (clearTimer) clearTimer(replayDurationTimer);
    replayDurationTimer = null;
  }, "clearReplayDurationTimer");
  const stopReplayRecorderSafely = /* @__PURE__ */ __name(() => {
    if (!stopReplayRecorder) return;
    try {
      stopReplayRecorder();
    } catch {
      stopReplayRecorder = null;
    }
    stopReplayRecorder = null;
  }, "stopReplayRecorderSafely");
  const replayErrorStageFor = /* @__PURE__ */ __name((reason) => {
    switch (reason) {
      case "recorder_script_failed":
        return "recorder_script";
      case "recorder_start_failed":
        return "recorder_start";
      case "recorder_runtime_error":
        return "recorder_runtime";
      case "missing_full_snapshot":
      case "full_snapshot_too_large":
        return "snapshot";
      case "upload_failed":
        return "upload";
      default:
        return null;
    }
  }, "replayErrorStageFor");
  const emitReplayErrorFor = /* @__PURE__ */ __name((targetReplayId, reason, eventCount, durationMs, chunkCount, uploadedBytes) => {
    const stage = replayErrorStageFor(reason);
    if (!stage) return;
    enqueue("lovable.replay_error", {
      replay_id: targetReplayId,
      reason,
      stage,
      recorder: "rrweb",
      sample_rate: replaySampleRate,
      event_count: eventCount,
      duration_ms: durationMs,
      chunk_count: chunkCount,
      uploaded_bytes: uploadedBytes
    });
  }, "emitReplayErrorFor");
  const emitReplayMetadataFor = /* @__PURE__ */ __name((targetReplayId, status, reason, eventCount, durationMs, chunkCount, uploadedBytes) => {
    enqueue("lovable.session_replay", {
      replay_id: targetReplayId,
      status,
      reason,
      recorder: "rrweb",
      sample_rate: replaySampleRate,
      event_count: eventCount,
      duration_ms: durationMs,
      chunk_count: chunkCount,
      uploaded_bytes: uploadedBytes
    });
    emitReplayErrorFor(targetReplayId, reason, eventCount, durationMs, chunkCount, uploadedBytes);
  }, "emitReplayMetadataFor");
  const emitReplayMetadata = /* @__PURE__ */ __name((status, reason) => {
    if (!replayId) return;
    emitReplayMetadataFor(
      replayId,
      status,
      reason,
      replayEventCount,
      replayDurationMs(),
      replayChunkSequence,
      replayUploadedBytes
    );
  }, "emitReplayMetadata");
  const stopReplayRecording = /* @__PURE__ */ __name((reason, dropQueuedEvents = false) => {
    if (!replayId || replayFinalized) return;
    stopReplayRecorderSafely();
    clearReplayDurationTimer();
    if (dropQueuedEvents) {
      replayQueue.length = 0;
      clearReplayFlushTimer();
    }
    emitReplayMetadata("stopped", reason);
    replayFinalized = true;
  }, "stopReplayRecording");
  const flushReplay = /* @__PURE__ */ __name((preferKeepalive = false) => {
    clearReplayFlushTimer();
    if (!replayId || replayQueue.length === 0 || !replayHasFullSnapshot) return;
    while (!replayFinalized && replayQueue.length > 0) {
      let count2 = Math.min(replayQueue.length, maxReplayEventsPerChunk);
      const chunkID = randomId();
      const chunkSequence = replayChunkSequence;
      let body = createReplayChunkBody(replayQueue.slice(0, count2), chunkID, chunkSequence);
      while (body && count2 > 1 && byteLength(body) > maxReplayBodyBytes) {
        count2 = Math.max(1, Math.floor(count2 / 2));
        body = createReplayChunkBody(replayQueue.slice(0, count2), chunkID, chunkSequence);
      }
      if (!body) return;
      const bodyBytes = byteLength(body);
      if (bodyBytes > maxReplayBodyBytes) {
        replayQueue.shift();
        replayEventCount = Math.max(0, replayEventCount - 1);
        continue;
      }
      if (replayChunkSequence >= maxReplayChunks || replayUploadedBytes + bodyBytes > maxReplayTotalBytes) {
        stopReplayRecording(replayChunkSequence >= maxReplayChunks ? "chunk_limit" : "size_limit", true);
        return;
      }
      replayQueue.splice(0, count2);
      replayChunkSequence++;
      replayUploadedBytes += bodyBytes;
      transmitReplayChunkBody(body, preferKeepalive);
      if (replayFinalized) return;
      if (replayChunkSequence >= maxReplayChunks) {
        stopReplayRecording("chunk_limit", true);
        return;
      }
    }
  }, "flushReplay");
  const scheduleReplayFlush = /* @__PURE__ */ __name(() => {
    if (replayFlushTimer) return;
    const setTimer = w.setTimeout || (typeof setTimeout === "function" ? setTimeout : null);
    if (!setTimer) {
      flushReplay();
      return;
    }
    replayFlushTimer = setTimer(flushReplay, replayFlushIntervalMs);
  }, "scheduleReplayFlush");
  const clearFlushTimer = /* @__PURE__ */ __name(() => {
    if (!flushTimer) return;
    const clearTimer = w.clearTimeout || (typeof clearTimeout === "function" ? clearTimeout : null);
    if (clearTimer) clearTimer(flushTimer);
    flushTimer = null;
  }, "clearFlushTimer");
  const flush = /* @__PURE__ */ __name(() => {
    clearFlushTimer();
    if (queue.length === 0) return;
    const events = queue.splice(0, batchSize);
    sendBatch(events);
    if (queue.length > 0) scheduleFlush();
  }, "flush");
  const scheduleFlush = /* @__PURE__ */ __name(() => {
    if (flushTimer) return;
    const setTimer = w.setTimeout || (typeof setTimeout === "function" ? setTimeout : null);
    if (!setTimer) {
      flush();
      return;
    }
    flushTimer = setTimer(flush, 250);
  }, "scheduleFlush");
  const rotatePageViewId = /* @__PURE__ */ __name(() => {
    pageViewId = randomId();
    return pageViewId;
  }, "rotatePageViewId");
  const shouldFlushImmediately = /* @__PURE__ */ __name((eventName) => eventName === "lovable.client_error" || eventName === "lovable.resource_error" || eventName === "lovable.replay_error", "shouldFlushImmediately");
  const refreshSession = /* @__PURE__ */ __name(() => {
    const now = Date.now();
    const expiresAt = now + rollingSessionTimeoutMs;
    if (sessionExpiresAt > now) {
      sessionExpiresAt = expiresAt;
      writeRollingSession(w.localStorage, rollingSessionStorageKey, sessionId, sessionExpiresAt);
      return false;
    }
    const sharedSession = readRollingSession(w.localStorage, rollingSessionStorageKey);
    if (sharedSession && sharedSession.expiresAt > now) {
      sessionId = sharedSession.value;
      sessionExpiresAt = expiresAt;
      writeRollingSession(w.localStorage, rollingSessionStorageKey, sessionId, sessionExpiresAt);
      return false;
    }
    sessionId = randomId();
    sessionExpiresAt = expiresAt;
    writeRollingSession(w.localStorage, rollingSessionStorageKey, sessionId, sessionExpiresAt);
    return true;
  }, "refreshSession");
  const queueEvent = /* @__PURE__ */ __name((eventName, eventSchemaVersion, properties = {}) => {
    const activeReplayId = replayId && !replayFinalized ? replayId : null;
    const eventId = randomId();
    const event = {
      event_id: eventId,
      event_name: eventName,
      event_schema_version: eventSchemaVersion,
      event_time: (/* @__PURE__ */ new Date()).toISOString(),
      anonymous_id: anonymousId,
      session_id: sessionId,
      page_view_id: pageViewId,
      properties
    };
    if (activeReplayId) event.replay_id = activeReplayId;
    if (shouldFlushImmediately(eventName)) {
      queue.unshift(event);
    } else {
      queue.push(event);
    }
    if (shouldFlushImmediately(eventName) || queue.length >= batchSize) {
      flush();
    } else {
      scheduleFlush();
    }
    return eventId;
  }, "queueEvent");
  const enqueue = /* @__PURE__ */ __name((eventName, properties = {}) => {
    const eventSchemaVersion = typeof eventName === "string" ? schemaVersionFor(eventName) : null;
    if (eventSchemaVersion === null) return null;
    if (eventName !== "lovable.session_started" && refreshSession()) {
      const sessionStartedSchemaVersion = schemaVersionFor("lovable.session_started");
      if (sessionStartedSchemaVersion !== null) {
        queueEvent("lovable.session_started", sessionStartedSchemaVersion, {
          reason: "new_session",
          is_new_anonymous_id: false
        });
      }
    }
    return queueEvent(eventName, eventSchemaVersion, properties);
  }, "enqueue");
  let lastPageViewPath = loc.pathname;
  const track = /* @__PURE__ */ __name((eventName, properties = {}) => {
    if (eventName === "lovable.page_viewed") {
      rotatePageViewId();
      if (typeof properties.url_path === "string") lastPageViewPath = properties.url_path;
    }
    return enqueue(eventName, properties);
  }, "track");
  const pathFromUrl = /* @__PURE__ */ __name((value) => {
    try {
      return new URL(value, loc.href).pathname;
    } catch {
      return null;
    }
  }, "pathFromUrl");
  const hostFromUrl = /* @__PURE__ */ __name((value) => {
    try {
      return new URL(value, loc.href).host.replace(/^www\./iu, "");
    } catch {
      return null;
    }
  }, "hostFromUrl");
  const serializeThrowable = /* @__PURE__ */ __name((value) => {
    const throwable = value && typeof value === "object" ? value : null;
    const stringifyPrimitive = /* @__PURE__ */ __name((input) => {
      if (typeof input === "string") return input;
      if (typeof input === "number" || typeof input === "boolean" || typeof input === "bigint") return String(input);
      return null;
    }, "stringifyPrimitive");
    const stack = stringifyPrimitive(throwable?.stack);
    return {
      message: stringifyPrimitive(throwable?.message) ?? stringifyPrimitive(value) ?? "Unknown error",
      name: stringifyPrimitive(throwable?.name),
      stack: stack ? stack.slice(0, 8e3) : null
    };
  }, "serializeThrowable");
  const emitPageView = /* @__PURE__ */ __name((navigationType, rotatePageView = true) => {
    if (rotatePageView) rotatePageViewId();
    const pagePath = loc.pathname;
    const properties = {
      url_path: pagePath,
      navigation_type: navigationType
    };
    if (typeof d?.referrer === "string" && d.referrer) {
      properties.referrer_host = hostFromUrl(d.referrer) ?? "";
      properties.referrer_path = pathFromUrl(d.referrer) ?? "";
    }
    lastPageViewPath = pagePath;
    enqueue("lovable.page_viewed", properties);
  }, "emitPageView");
  const emitPageViewIfPathChanged = /* @__PURE__ */ __name((navigationType) => {
    if (loc.pathname === lastPageViewPath) return;
    emitPageView(navigationType);
  }, "emitPageViewIfPathChanged");
  const startReplayRecording = /* @__PURE__ */ __name(() => {
    const record = replayMode === "rrweb" && isReplaySampledIn && w.rrweb && typeof w.rrweb.record === "function" ? w.rrweb.record : null;
    if (!record || replayId) return;
    replayId = randomId();
    replayStartedAt = perf.now();
    replayFinalized = false;
    replayChunkSequence = 0;
    replayUploadedBytes = 0;
    replayHasFullSnapshot = false;
    try {
      const stop = record({
        emit(event) {
          if (replayFinalized) return;
          if (!event || typeof event !== "object") return;
          if (replayDurationMs() >= maxReplayDurationMs) {
            stopReplayRecording("duration_limit", true);
            return;
          }
          const eventType = replayEventType(event);
          if (!canSendReplayEvent(event)) {
            if (eventType === 2 && !replayHasFullSnapshot) {
              stopReplayRecording("full_snapshot_too_large", true);
            }
            return;
          }
          if (eventType === 3 && !replayHasFullSnapshot) return;
          if (eventType === 2) replayHasFullSnapshot = true;
          replayEventCount += 1;
          replayQueue.push(event);
          if (replayQueue.length >= maxReplayEventsPerChunk) {
            flushReplay();
          } else {
            scheduleReplayFlush();
          }
        },
        blockClass: "rr-block",
        blockSelector: replayBlockSelector,
        collectFonts: false,
        ignoreClass: "rrweb-ignore",
        ignoreSelector: replayIgnoreSelector,
        inlineImages: false,
        maskAllInputs: true,
        maskInputFn: maskReplayText,
        maskInputOptions: {
          checkbox: false,
          color: false,
          date: true,
          "datetime-local": true,
          email: true,
          month: true,
          number: true,
          password: true,
          radio: false,
          range: false,
          search: true,
          select: true,
          tel: true,
          text: true,
          textarea: true,
          time: true,
          url: true,
          week: true
        },
        maskTextClass: "rr-mask",
        maskTextFn: maskReplayText,
        maskTextSelector: replayMaskTextSelector,
        recordAfter: "load",
        recordCanvas: false,
        recordCrossOriginIframes: false
      });
      if (replayFinalized) {
        if (typeof stop === "function") {
          try {
            stop();
          } catch {
          }
        }
        return;
      }
      if (typeof stop === "function") stopReplayRecorder = stop;
      const setTimer = w.setTimeout || (typeof setTimeout === "function" ? setTimeout : null);
      if (setTimer && typeof record.takeFullSnapshot === "function") {
        setTimer(() => {
          if (!replayId || replayFinalized) return;
          try {
            record.takeFullSnapshot?.(false);
          } catch {
          }
        }, 1e3);
      }
      if (setTimer) {
        replayDurationTimer = setTimer(() => stopReplayRecording("duration_limit", true), maxReplayDurationMs);
      }
      emitReplayMetadata("started", "recorder_available");
    } catch {
      emitReplayMetadata("stopped", "recorder_start_failed");
      replayId = null;
      replayEventCount = 0;
      replayStartedAt = 0;
      replayFinalized = false;
      replayHasFullSnapshot = false;
      stopReplayRecorder = null;
      clearReplayDurationTimer();
    }
  }, "startReplayRecording");
  const ensureReplayRecorder = /* @__PURE__ */ __name(() => {
    if (replayMode !== "rrweb" || !isReplaySampledIn || replayId || replayRecorderLoading) return;
    if (w.rrweb && typeof w.rrweb.record === "function") {
      startReplayRecording();
      return;
    }
    const head = d?.head;
    if (!d?.createElement || !head?.appendChild) return;
    const recorderScript = d.createElement("script");
    replayRecorderLoading = true;
    recorderScript.async = true;
    recorderScript.src = replayScriptUrl;
    recorderScript.onload = () => {
      replayRecorderLoading = false;
      if (!w.rrweb || typeof w.rrweb.record !== "function") {
        emitReplayMetadataFor(randomId(), "stopped", "recorder_script_failed", 0, 0, 0, 0);
        return;
      }
      startReplayRecording();
    };
    recorderScript.onerror = () => {
      replayRecorderLoading = false;
      emitReplayMetadataFor(randomId(), "stopped", "recorder_script_failed", 0, 0, 0, 0);
    };
    head.appendChild(recorderScript);
  }, "ensureReplayRecorder");
  const finalizeReplayRecording = /* @__PURE__ */ __name((reason) => {
    if (!replayId || replayFinalized) return;
    stopReplayRecorderSafely();
    clearReplayDurationTimer();
    if (!replayHasFullSnapshot) {
      stopReplayRecording("missing_full_snapshot", true);
      return;
    }
    flushReplay(true);
    if (!replayFinalized) stopReplayRecording(reason);
  }, "finalizeReplayRecording");
  const stopReplayRecordingOnRuntimeError = /* @__PURE__ */ __name((event) => {
    if (!replayId || replayFinalized) return;
    const errorEvent = event && typeof event === "object" ? event : null;
    const errorRecord = errorEvent?.error && typeof errorEvent.error === "object" ? errorEvent.error : null;
    const filename = typeof errorEvent?.filename === "string" ? errorEvent.filename : "";
    const stack = typeof errorRecord?.stack === "string" ? errorRecord.stack : "";
    const isRecorderError = filename.includes("/__l5e/rrweb-record") || stack.includes("/__l5e/rrweb-record");
    if (isRecorderError) {
      stopReplayRecording("recorder_runtime_error", true);
    }
  }, "stopReplayRecordingOnRuntimeError");
  const webVitalRating = /* @__PURE__ */ __name((metric, value) => {
    const thresholds = {
      CLS: [0.1, 0.25],
      FCP: [1800, 3e3],
      INP: [200, 500],
      LCP: [2500, 4e3],
      TTFB: [800, 1800]
    };
    const threshold = thresholds[metric];
    if (!threshold) return "good";
    if (value <= threshold[0]) return "good";
    return value <= threshold[1] ? "needs-improvement" : "poor";
  }, "webVitalRating");
  const webVitalUnit = /* @__PURE__ */ __name((metric) => metric === "CLS" ? "score" : "millisecond", "webVitalUnit");
  const emitWebVital = /* @__PURE__ */ __name((metric, value, delta = value, id2 = randomId()) => {
    if (!Number.isFinite(value) || value < 0) return;
    enqueue("lovable.web_vital", {
      metric,
      value,
      unit: webVitalUnit(metric),
      delta,
      rating: webVitalRating(metric, value),
      id: id2,
      navigation_type: "initial_load"
    });
  }, "emitWebVital");
  const performanceEntryValue = /* @__PURE__ */ __name((entry, key) => {
    const value = entry[key];
    return typeof value === "number" && Number.isFinite(value) ? value : null;
  }, "performanceEntryValue");
  const observePerformanceEntries = /* @__PURE__ */ __name((entryType, callback) => {
    const ObserverCtor = w.PerformanceObserver;
    if (!ObserverCtor) return false;
    const supportedEntryTypes = ObserverCtor.supportedEntryTypes;
    if (Array.isArray(supportedEntryTypes) && !supportedEntryTypes.includes(entryType)) return false;
    try {
      const observer = new ObserverCtor((list) => {
        const entries = typeof list.getEntries === "function" ? list.getEntries() : [];
        for (const entry of entries) {
          if (entry && typeof entry === "object") callback(entry);
        }
      });
      observer.observe(
        entryType === "event" ? { type: entryType, buffered: true, durationThreshold: 40 } : { type: entryType, buffered: true }
      );
      return true;
    } catch {
      return false;
    }
  }, "observePerformanceEntries");
  const observeWebVitals = /* @__PURE__ */ __name(() => {
    let fcpReported = false;
    let latestLcp = null;
    let clsValue = 0;
    let maxInp = 0;
    let finalized = false;
    const metricIds = {};
    const reportedValues = {};
    const metricId = /* @__PURE__ */ __name((metric) => {
      metricIds[metric] = metricIds[metric] || randomId();
      return metricIds[metric];
    }, "metricId");
    const reportMetric = /* @__PURE__ */ __name((metric, value) => {
      if (reportedValues[metric] === value) return;
      const previousValue = reportedValues[metric];
      reportedValues[metric] = value;
      emitWebVital(metric, value, previousValue === void 0 ? value : value - previousValue, metricId(metric));
    }, "reportMetric");
    const navigationEntries = typeof perf.getEntriesByType === "function" ? perf.getEntriesByType("navigation") : [];
    const navigationEntry = navigationEntries[0] && typeof navigationEntries[0] === "object" ? navigationEntries[0] : null;
    const responseStart = navigationEntry ? performanceEntryValue(navigationEntry, "responseStart") : null;
    const requestStart = navigationEntry ? performanceEntryValue(navigationEntry, "requestStart") : null;
    const fetchStart = navigationEntry ? performanceEntryValue(navigationEntry, "fetchStart") : null;
    const ttfbStart = requestStart ?? fetchStart;
    if (responseStart !== null && ttfbStart !== null && responseStart >= ttfbStart) {
      emitWebVital("TTFB", responseStart - ttfbStart);
    }
    observePerformanceEntries("paint", (entry) => {
      if (fcpReported || entry.name !== "first-contentful-paint") return;
      const startTime = performanceEntryValue(entry, "startTime");
      if (startTime === null) return;
      fcpReported = true;
      emitWebVital("FCP", startTime);
    });
    observePerformanceEntries("largest-contentful-paint", (entry) => {
      latestLcp = performanceEntryValue(entry, "startTime") ?? performanceEntryValue(entry, "renderTime") ?? performanceEntryValue(entry, "loadTime") ?? latestLcp;
    });
    const clsObserverStarted = observePerformanceEntries("layout-shift", (entry) => {
      if (entry.hadRecentInput === true) return;
      const value = performanceEntryValue(entry, "value");
      if (value !== null) clsValue += value;
    });
    observePerformanceEntries("event", (entry) => {
      const interactionId = performanceEntryValue(entry, "interactionId");
      if (interactionId === null || interactionId <= 0) return;
      const duration = performanceEntryValue(entry, "duration");
      if (duration !== null && duration > maxInp) maxInp = duration;
    });
    return (final = false) => {
      if (finalized) return;
      if (latestLcp !== null) reportMetric("LCP", latestLcp);
      if (clsObserverStarted) reportMetric("CLS", clsValue);
      if (maxInp > 0) reportMetric("INP", maxInp);
      if (final) finalized = true;
    };
  }, "observeWebVitals");
  const patchHistory = /* @__PURE__ */ __name((method, navigationType) => {
    const historyObject = w.history;
    const original = historyObject && historyObject[method];
    if (!historyObject || typeof original !== "function") return;
    historyObject[method] = function(...args) {
      const result = original.apply(this, args);
      emitPageViewIfPathChanged(navigationType);
      return result;
    };
  }, "patchHistory");
  const normalizeCaptureExceptionOptions = /* @__PURE__ */ __name((options) => {
    const record = options && typeof options === "object" && !Array.isArray(options) ? options : {};
    const mechanism = typeof record.mechanism === "string" ? record.mechanism : "manual";
    const severity = typeof record.severity === "string" ? record.severity : "error";
    return {
      mechanism: ["manual", "onerror", "unhandledrejection", "react_error_boundary"].includes(mechanism) ? mechanism : "manual",
      handled: typeof record.handled === "boolean" ? record.handled : true,
      severity: ["error", "warning", "info"].includes(severity) ? severity : "error"
    };
  }, "normalizeCaptureExceptionOptions");
  w.__lovableEvents = Object.assign(existing, {
    __userAppEventsSdkVersion: version2,
    track,
    flush,
    identify(identity) {
      const identityRecord = identity && typeof identity === "object" ? identity : null;
      appUserId = typeof identityRecord?.user_id === "string" ? identityRecord.user_id : null;
    },
    getIdentity() {
      return { anonymous_id: anonymousId, app_user_id_hash_available: !!appUserId };
    },
    getReplay() {
      return { replay_id: replayId, active: !!replayId && !replayFinalized, event_count: replayEventCount };
    },
    resetIdentity() {
      appUserId = null;
    },
    captureException(error, context = {}, options = {}) {
      const throwable = serializeThrowable(error);
      const captureOptions = normalizeCaptureExceptionOptions(options);
      const captureContext = context && typeof context === "object" && !Array.isArray(context) ? context : {};
      enqueue("lovable.client_error", {
        occurrence_id: randomId(),
        ...throwable,
        ...captureOptions,
        context: captureContext
      });
    },
    startSpan(name, attributes = {}) {
      const startTime = (/* @__PURE__ */ new Date()).toISOString();
      const start = perf.now();
      return {
        end(status = "ok") {
          enqueue("lovable.frontend_span", {
            name,
            start_time: startTime,
            status,
            duration_ms: Math.max(0, perf.now() - start),
            attributes
          });
        }
      };
    }
  });
  if (w.addEventListener) {
    w.addEventListener("error", stopReplayRecordingOnRuntimeError, true);
  }
  ensureReplayRecorder();
  const finalizeWebVitals = observeWebVitals();
  if (w.addEventListener) {
    w.addEventListener(
      "error",
      (event) => {
        const errorEvent = event && typeof event === "object" ? event : null;
        const target = errorEvent?.target && typeof errorEvent.target === "object" ? errorEvent.target : null;
        let resourceUrl = null;
        if (target && target !== w) {
          if (typeof target.src === "string") {
            resourceUrl = target.src;
          } else if (typeof target.href === "string") {
            resourceUrl = target.href;
          }
        }
        if (resourceUrl !== null) {
          const resourceType = typeof target?.tagName === "string" ? target.tagName.toLowerCase() : "unknown";
          enqueue("lovable.resource_error", {
            resource_type: resourceType,
            url_host: hostFromUrl(resourceUrl) ?? "",
            failure_type: "load_error",
            element: resourceType,
            url_path: pathFromUrl(resourceUrl),
            mechanism: "resource_load",
            handled: false,
            severity: "error"
          });
          return;
        }
        const throwable = serializeThrowable(errorEvent?.error || errorEvent?.message);
        enqueue("lovable.client_error", {
          occurrence_id: randomId(),
          ...throwable,
          filename: typeof errorEvent?.filename === "string" ? pathFromUrl(errorEvent.filename) : null,
          lineno: typeof errorEvent?.lineno === "number" ? errorEvent.lineno : null,
          colno: typeof errorEvent?.colno === "number" ? errorEvent.colno : null,
          mechanism: "onerror",
          handled: false,
          severity: "error"
        });
      },
      true
    );
    w.addEventListener("unhandledrejection", (event) => {
      const rejectionEvent = event && typeof event === "object" ? event : null;
      const throwable = serializeThrowable(rejectionEvent?.reason);
      enqueue("lovable.client_error", {
        occurrence_id: randomId(),
        ...throwable,
        mechanism: "unhandledrejection",
        handled: false,
        severity: "error"
      });
    });
    w.addEventListener("pagehide", () => {
      finalizeWebVitals(true);
      finalizeReplayRecording("pagehide");
      flush();
    });
    w.addEventListener("popstate", () => emitPageViewIfPathChanged("popstate"));
    w.addEventListener("hashchange", () => emitPageView("hashchange"));
  }
  if (d && typeof d.addEventListener === "function") {
    d.addEventListener("visibilitychange", () => {
      if (d.visibilityState === "hidden") {
        finalizeWebVitals();
        flushReplay(true);
        flush();
      }
    });
  }
  patchHistory("pushState", "push_state");
  patchHistory("replaceState", "replace_state");
  if (sessionIdentity.created) {
    enqueue("lovable.session_started", {
      reason: "new_session",
      is_new_anonymous_id: anonymousIdentity.created
    });
  }
  emitPageView("initial_load", false);
})(window, "0.1.1-replay-recorder-fix", {"lovable.session_started":1,"lovable.page_viewed":1,"lovable.client_error":1,"lovable.frontend_span":1,"lovable.interaction":1,"lovable.form_submitted":1,"lovable.resource_error":1,"lovable.session_replay":1,"lovable.replay_error":1,"lovable.web_vital":1}, 50, 51200, 524288, 100, 5242880, 50, 600000, 2000);})();