"""Runtime deployment topology for snippen-booking (SVG output)."""

from pathlib import Path

from diagrams import Cluster, Diagram, Edge
from diagrams.onprem.client import Client, User
from diagrams.onprem.compute import Server
from diagrams.onprem.database import MySQL
from diagrams.onprem.network import Apache
from diagrams.programming.language import PHP

OUT_FILE = str(Path(__file__).resolve().parents[1] / "out" / "deployment-topology")

with Diagram(
    "Snippen Booking - Runtime Topology",
    filename=OUT_FILE,
    outformat="svg",
    show=False,
    direction="LR",
):
    visitor = User("Visitor / Booker")
    board = Client("Board Admin")

    with Cluster("WordPress Hosting Environment"):
        web = Apache("Apache Web Server")
        wp = PHP("WordPress + Snippen Plugin\n(REST, AJAX, WP-Cron)")
        db = MySQL("WordPress MySQL\n(bookings, templates, logs)")

    with Cluster("External Services"):
        vipps = Server("Vipps ePayment API")
        notif = Server("SMS & Email Gateways")

    visitor >> Edge(label="HTTPS") >> web
    board >> Edge(label="HTTPS / WP Admin") >> web
    web >> Edge(label="mod_php / FPM") >> wp
    wp >> Edge(label="SQL") >> db
    wp >> Edge(label="REST / Webhook") >> vipps
    wp >> Edge(label="HTTPS / SMTP") >> notif
