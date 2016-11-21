# This script will run on the Raspberry Pi

from autobahn.twisted.websocket import WebSocketClientProtocol, \
    WebSocketClientFactory

ROBOT_PASSWORD = 'robot'

class RobotClientProtocol(WebSocketClientProtocol):

    def onConnect(self, response):
        print("Server connected: {0}".format(response.peer))

    def onOpen(self):
        print("WebSocket connection open.")
        self.sendMessage(ROBOT_PASSWORD.encode('utf8'))

        def sendReadings():
            # change this to actually read from sensors
            self.sendMessage('{"t":"test","l":"test"}'.encode('utf8'))
            self.factory.reactor.callLater(1, sendReadings)

        # start sending readings every second ..
        sendReadings()

    def onMessage(self, payload, isBinary):
        # change this to actually send instructions to motor
        print("Text message received: {0}".format(payload.decode('utf8')))

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