# This server acts as the go-between in many php tasks that are running on behalf of long-poll clients.

import socket
import threading
from threading import Thread
from SocketServer import ThreadingMixIn
import select
import json
from datetime import datetime
import time

TCP_IP = '127.0.0.1'
TCP_PORT = 23456
CLIENT_LIFETIME = 10 # each client connection lives for 2 seconds

class Room():
    s_roomCode = ""
    a_clients = []
    a_events = []
    t_changeTime = None
    l_eventLock = None
    l_clientLock = None
    l_timeLock = None

    def __init__(self, s_roomCode):
        self.s_roomCode = s_roomCode
        self.t_changeTime = datetime.now()
        self.l_eventLock = threading.Lock()
        self.l_clientLock = threading.Lock()
        self.l_timeLock = threading.Lock()

    def checkTimeKill(self):
        if (self.s_roomCode == ""):
            return False
        i_numClients = 0
        with self.l_clientLock:
            i_numClients = len(self.a_clients)
        if (i_numClients == 0):
            t_delta = 0
            with self.l_timeLock:
                t_delta = datetime.now() - self.t_changeTime
            if (t_delta.total_seconds() > 300):
                return True
        return True

    def checkTimeKillAll(self):
        roomsToRemove = []
        for roomCode in a_rooms:
            room = a_rooms[roomCode]
            if (room.checkTimeKill()):
                roomsToRemove.append(room)
        for roomCode in roomsToRemove:
            del a_rooms[roomCode]

    def getClientsListCopy(self):
        with self.l_timeLock:
            self.t_changeTime = datetime.now()
        with self.l_clientLock:
            a_clientsCopy = []
            for o_client in self.a_clients:
                a_clientsCopy.append(o_client)
            return a_clientsCopy

    def hasClient(self, o_client):
        with self.l_timeLock:
            self.t_changeTime = datetime.now()
        with self.l_clientLock:
            return o_client in self.a_clients

    def appendClient(self, o_client):
        with self.l_timeLock:
            self.t_changeTime = datetime.now()
        with self.l_clientLock:
            if (o_client in self.a_clients):
                return
            self.a_clients.append(o_client)

    def removeClient(self, o_client):
        with self.l_timeLock:
            self.t_changeTime = datetime.now()
        with self.l_clientLock:
            if not (o_client in self.a_clients):
                return
            self.a_clients.remove(o_client)

    def getLaterEvent(self, t_time):
        with self.l_timeLock:
            self.t_changeTime = datetime.now()
        with self.l_eventLock:
            for i in range(len(self.a_events), 0, -1):
                event = self.a_events[i]
                if (event.t_serverTime > t_time):
                    return event
            return None

    # puts the given event at the front of the queue
    def appendEvent(self, o_event):
        with self.l_timeLock:
            self.t_changeTime = datetime.now()
        with self.l_eventLock:
            self.a_events.append({ 't_serverTime': datetime.now(), 'event': o_event })
            while (len(self.a_events) > 100):
                self.a_events.remove(self.a_events[0])

# Multithreaded Python server : TCP Server Socket Thread Pool
class ClientThread(Thread):
    conn = None
    ip = "127.0.0.1"
    port = 0
    b_abort = False
    s_roomCode = ""
    a_latestEvents = []
    t_createTime = None
    l_abortLock = None
 
    def __init__(self, conn, ip, port):
        Thread.__init__(self)
        if (ip != "127.0.0.1"):
            raise Exception()
        self.conn = conn
        self.ip = ip
        self.port = port
        self.t_createTime = datetime.now()
        self.l_abortLock = threading.Lock()
        print "[+] New client socket thread started for " + ip + ":" + str(port)

    def __del__(self):
        self.tryRemove()
        print("[-] Client connection closed")

    def removeMe(self):
        with self.l_abortLock:
            self.b_abort = True
        self.tryRemove()
        o_clientKiller1.appendClient(self)
        self.conn.close()
        if (self in a_threads):
            a_threads.remove(self)

    def tryRemove(self, s_roomCode = None):
        if (s_roomCode is None):
            if (self.s_roomCode == None):
                return
            s_roomCode = self.s_roomCode;
        try:
            a_rooms[s_roomCode].removeClient(self)
            if (s_roomCode == self.s_roomCode):
                self.s_roomCode = None
        except Exception as e:
            pass

    def checkTimeKill(self, s_roomCode, i_timeoutSecs):
        # make sure I'm in the right room
        if (s_roomCode != self.s_roomCode):
            # print(">>>>>>>>>>>>>>>>>>>")
            print("client s_roomCode " + self.s_roomCode + " != " + s_roomCode)
            self.tryRemove(s_roomCode)
            room = a_rooms[self.s_roomCode]
            if not room.hasClient(self):
                room.appendClient(self)

        # have I been alive for too long?
        t_delta = datetime.now() - self.t_createTime
        if (t_delta.total_seconds() > i_timeoutSecs):
            self.b_abort = True
            self.tryRemove()

    def checkTimeKillAll(self, s_roomCode, i_timeoutSecs):
        # find the clients that need to be removed
        a_roomClients = []
        for client in a_rooms[s_roomCode].getClientsListCopy():
            client.checkTimeKill(s_roomCode, i_timeoutSecs)

        # run a similar check to see if the room needs to be killed
        a_rooms[""].checkTimeKillAll()

    def checkConn(self):
        try:
            if (self.conn.fileno() < 0):
                return False
        except Exception as e:
            return False
        return True

    def tryRecv(self, default = ""):
        try:
            t_now = datetime.now()
            t_prev = t_now
            i_waitTime = CLIENT_LIFETIME
            ret = ""

            # receive data until there is no more data to be recieved
            while (t_now - t_prev < i_waitTime):
                count = self.conn.recv_into(part, 1, flags=MSG_DONTWAIT)

                # check if we've received anything
                if (count > 0):
                    
                    # append this part to the return value
                    ret += part
                    t_prev = t_now

                    # If we've received part of a message, then we know the rest
                    # of the message will be coming in much less than CLIENT_LIFETIME seconds.
                    i_waitTime = 0.1

                # nothing to be read right now
                else:
                    threading.sleep(0.1)

                t_now = datetime.now()
                with self.l_abortLock:
                    if (self.b_abort):
                        break

            # done receiving a message
            return ret

        except Exception as e:
            return default

    def trySend(self, value):
        try:
            # try to send the message
            self.conn.send(json.dumps(value))
            return True
        except Exception as e:
            return False

    def checkLatestEvents(self):
        room = a_rooms[self.s_roomCode]

        # find the latest event time from my latestEvents
        latestTime = 0
        for event in self.a_latestEvents:
            if (event.t_serverTime > latestTime):
                latestTime = event.t_serverTime

        # check for events that have a later time than my latest time
        laterEvent = room.getLaterEvent(latestTime)
        if (laterEvent != None):
            # found a later event, send it back to the PHP client
            b_ret = self.trySend(laterEvent)
            if (b_ret):
                # successfully sent a message, so we know PHP client has disconnected and
                # is no longer listening remove this instance
                with self.l_abortLock:
                    self.b_abort = True
            return b_ret

        # events up-to-date
        return False

    def pushEvent(self, event):
        if self.trySend(event):
            with self.l_abortLock:
                self.b_abort = True
            return True
        return False

    def setRoomCode(self, s_roomCode):
        self.s_roomCode = s_roomCode
        if not (self.s_roomCode in a_rooms):
            a_rooms[self.s_roomCode] = Room(self.s_roomCode)
        room = a_rooms[self.s_roomCode]
        self.checkTimeKillAll(self.s_roomCode, CLIENT_LIFETIME)
        room.appendClient(self)
 
    def run(self):
        while True:
            if not self.checkConn():
                break

            ready = select.select([self.conn], [], [], 1)
            b_stop = False
            if ready[0]:
                s_data = self.tryRecv()
                with self.l_abortLock:
                    if (self.b_abort):
                        break

                s_data = s_data.strip()
                if (len(s_data) <= 0):
                    continue

                print("[:] Received message \"" + s_data + "\" from client")
                if (s_data.startswith("disconnect")):
                    b_stop = True

                elif (s_data.startswith("subscribe ")):
                    self.tryRemove()
                    a_data = json.loads(s_data[len("subscribe "):])
                    self.setRoomCode(a_data['roomCode'])
                    self.a_latestEvents = a_data['latestEvents']
                    self.checkLatestEvents()
                
                elif (s_data.startswith("push ")):
                    a_data = json.loads(s_data[len("push "):])
                    self.setRoomCode(a_data['roomCode'])
                    room = a_rooms[self.s_roomCode]
                    room.appendEvent(a_data['event'])
                    for client in room.getClientsListCopy():
                        client.pushEvent(a_data['event'])
            else:
                time.sleep(0.1)
            
            with self.l_abortLock:
                if b_stop or self.b_abort:
                    break

        # This client has timed out or is otherwise done.
        # Remove myself from my room and give myself 1 second before getting deleted.
        self.removeMe()

class ClientKiller():
    a_clients = []
    t_tickTime = None
    o_clientKillerNext = None
    l_clientLock = None

    def __init__(self, o_clientKillerNext = None):
        self.t_tickTime = datetime.now()
        self.o_clientKillerNext = o_clientKillerNext
        self.l_clientLock = threading.Lock()

    def appendClient(self, o_client):
        with self.l_clientLock:
            if (o_client in self.a_clients):
                return
            self.a_clients.append(o_client)
        self.checkTime()

    def removeClient(self, o_client):
        with self.l_clientLock:
            if not (o_client in self.a_clients):
                return
            self.a_clients.remove(o_client)
        self.checkTime()

    def checkTime(self):
        t_now = datetime.now()
        t_delta = t_now - self.t_tickTime
        if (t_delta.total_seconds() > 1):
            with self.l_clientLock:
                for o_client in self.a_clients:
                    if (self.o_clientKillerNext != None):
                        self.o_clientKillerNext.appendClient(o_client)
                    else:
                        o_client.__del__()
                self.a_clients = []
            self.t_tickTime = datetime.now()

# Multithreaded Python server : TCP Server Socket Program Stub
tcpServer = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
tcpServer.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
tcpServer.bind((TCP_IP, TCP_PORT))

# On getting used up, dieing clients first get sent to o_clientKiller1, then
# to o_clientKiller2, then they get deleted.
# We use two client killers so that it takes at least 1 second to kill a client
# in case there are other references to the client lieing around, trying to
# do something with it.
o_clientKiller2 = ClientKiller()
o_clientKiller1 = ClientKiller(o_clientKiller2)

a_rooms = { "": Room("") }
a_threads = []

while True:
    print("server listening at " + TCP_IP + ":" + str(TCP_PORT) + " (" + str(datetime.now()) + ")")

    conn = None
    try:
        tcpServer.listen(4)
        # print "Multithreaded Python server : Waiting for connections from TCP clients..."
        (conn, (ip,port)) = tcpServer.accept()
        if conn is None:
            break
        try:
            conn.setblocking(CLIENT_LIFETIME)
            newthread = ClientThread(conn,ip,port)
            newthread.start()
            newthread.checkTimeKillAll("", CLIENT_LIFETIME)
            a_rooms[""].a_clients.append(newthread)
            print("thread count: " + str(len(a_threads)))
            a_threads.append(newthread)
        except Exception as e:
            print "Bad ip \"" + str(ip) + "\", port \"" + str(port) + "\", or camera name \"\": " + str(e)
    except KeyboardInterrupt:
        if conn:
            conn.close()
        break

for t in a_threads:
    t.abort = True