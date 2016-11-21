import sys

from twisted.internet import reactor
from twisted.python import log
from twisted.web.server import Site
from twisted.web.static import File

from autobahn.twisted.websocket import WebSocketServerFactory, \
    WebSocketServerProtocol, \
    listenWS

DRIVER_PASSWORD = 'driver'
ROBOT_PASSWORD = 'robot'

class BroadcastServerProtocol(WebSocketServerProtocol):
    def onOpen(self):
        self.factory.registerViewer(self)

    def onMessage(self, payload, isBinary):
        if not isBinary:
            msg = "{}".format(payload.decode('utf8'))
            if msg == DRIVER_PASSWORD:
                self.factory.registerDriver(self)
            elif msg == ROBOT_PASSWORD:
                self.factory.registerRobot(self)
            elif self == self.factory.driver:
                self.factory.sendCommand(msg)
            elif self == self.factory.robot:
                self.factory.broadcast(msg)

    def connectionLost(self, reason):
        WebSocketServerProtocol.connectionLost(self, reason)
        self.factory.unregister(self)


class BroadcastServerFactory(WebSocketServerFactory):
    def __init__(self, url):
        WebSocketServerFactory.__init__(self, url)
        self.viewers = []
        self.driver = None
        self.robot = None

    def registerViewer(self, client):
        if client not in self.viewers:
            print("registered viewer {}".format(client.peer))
            self.viewers.append(client)

    def registerDriver(self, client):
        if self.driver is None:
            print("registered driver {}".format(client.peer))
            self.driver = client
            self.driver.sendMessage('ACK'.encode('utf8'))
        else:
            client.sendMessage('DIE'.encode('utf8'))
            self.unregister(client)

    def registerRobot(self, client):
        if self.robot is None:
            print("registered robot {}".format(client.peer))
            self.robot = client
            if client in self.viewers:
                self.viewers.remove(client)
        else:
            self.unregister(client)

    def unregister(self, client):
        if client in self.viewers:
            print("unregistered viewer {}".format(client.peer))
            self.viewers.remove(client)
        if client == self.driver:
            print("unregistered driver {}".format(client.peer))
            self.driver = None
        if client == self.robot:
            print("unregistered robot {}".format(client.peer))
            self.robot = None

    def sendCommand(self, msg):
        self.robot.sendMessage(msg.encode('utf8'))
        print("sent command '{}' ..".format(msg))

    def broadcast(self, msg):
        #print("broadcasting message '{}' ..".format(msg))
        for c in self.viewers:
            c.sendMessage(msg.encode('utf8'))
            #print("message sent to {}".format(c.peer))

if __name__ == '__main__':

    log.startLogging(sys.stdout)

    ServerFactory = BroadcastServerFactory

    factory = ServerFactory(u"ws://127.0.0.1:9000")
    factory.protocol = BroadcastServerProtocol
    listenWS(factory)

    webdir = File(".")
    web = Site(webdir)
    reactor.listenTCP(8080, web)

    reactor.run()