# This script will run on the Raspberry Pi
import random
from autobahn.twisted.websocket import WebSocketClientProtocol, \
    WebSocketClientFactory

ROBOT_PASSWORD = 'robot9000'

# implement this
def controlMotor(payload):
    print("Input received: {0}".format(payload.decode('utf8')))

# implement this
def readTemperature():
    return str(random.random())

# implement this
def readLight():
    return str(random.random())

class RobotClientProtocol(WebSocketClientProtocol):

    def onConnect(self, response):
        print("Server connected: {0}".format(response.peer))

    def onOpen(self):
        print("WebSocket connection open.")
        # ask the server if I can be a robot
        self.sendMessage(ROBOT_PASSWORD.encode('utf8'))

        def sendReadings():
            light = readLight()
            temperature = readTemperature()
            self.sendMessage('{"t":"'+light+'","l":"'+temperature+'"}'.encode('utf8'))
            self.factory.reactor.callLater(1, sendReadings)

        # start sending readings every second ..
        sendReadings()

    def onMessage(self, payload, isBinary):
        controlMotor(payload)

    def onClose(self, wasClean, code, reason):
        print("WebSocket connection closed: {0}".format(reason))


if __name__ == '__main__':

    import sys

    from twisted.python import log
    from twisted.internet import reactor

    log.startLogging(sys.stdout)

    factory = WebSocketClientFactory(u"ws://127.0.0.1:9000")
    factory.protocol = RobotClientProtocol

    reactor.connectTCP(sys.argv[1], 9000, factory)
reactor.run()
