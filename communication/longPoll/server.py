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
CLIENT_LIFETIME = 10 # each client connection lives for 10 seconds

class Room():
    l_roomLock = threading.RLock()

    def __init__(self, s_roomCode):
        self.s_roomCode = s_roomCode
        self.a_clients = []
        self.a_events = []
        self.t_changeTime = datetime.now()
        self.l_eventLock = threading.RLock()
        self.l_clientLock = threading.RLock()
        self.l_timeLock = threading.RLock()
        self.i_nextEventId = -1

    @staticmethod
    def getRoom(s_roomCode, b_createIfNotExisting):
        with Room.l_roomLock:
            if not (s_roomCode in a_rooms):
                if (b_createIfNotExisting):
                    room = Room(s_roomCode)
                    a_rooms[s_roomCode] = room
                    return room
                else:
                    return None
            else:
                return a_rooms[s_roomCode]

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
        return False

    def checkTimeKillAll(self):
        roomsToRemove = []

        # create a copy of the a_rooms list to limit the scope of the thread lock
        a_roomsCopy = {}
        with Room.l_roomLock:
            for s_roomCode in a_rooms:
                a_roomsCopy[s_roomCode] = a_rooms[s_roomCode]

        # look for instances of rooms that need to be removed
        for s_roomCode in a_roomsCopy:
            try:
                room = a_roomsCopy[s_roomCode]
                if (room.checkTimeKill()):
                    roomsToRemove.append(s_roomCode)
            except Exception as e:
                pass # here in case the room disappears between the copy phase and now

        # remove those instances
        with Room.l_roomLock:
            try:
                for s_roomCode in roomsToRemove:
                    del a_rooms[s_roomCode]
            except Exception as e:
                pass # here in case the room disappears between the copy phase and now

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

    def getLatestEvents(self):
        with self.l_timeLock:
            self.t_changeTime = datetime.now()
        # return a list of all event ids
        a_ids = []
        with self.l_eventLock:
            for i in range(len(self.a_events)):
                event = self.a_events[i]
                a_ids.append(event['i_id'])
                if (isinstance(a_ids, bool)):
                    print("a_ids is bool after appending " + str(event['i_id']) + "/" + str(event))
                    break
        return a_ids

    def getMissingEvent(self, a_events):
        with self.l_timeLock:
            self.t_changeTime = datetime.now()
        with self.l_eventLock:
            # print("trying to find missing events")
            for i in range(len(self.a_events)):
                event = self.a_events[i]

                # check if this event exists in the given events
                if not (event['i_id'] in a_events):
                    # print("found missing event")
                    return event
            return None

    def getEventIds(self):
        with self.l_eventLock:
            return [x['i_id'] for x in self.a_events]

    def getEventById(self, eventId):
        with self.l_eventLock:
            for i in range(len(self.a_events)):
                event = self.a_events[i]
                if (event['i_id'] == eventId):
                    return event;
        return None;

    # puts the given event at the front of the queue
    def appendEvent(self, o_event):
        with self.l_timeLock:
            self.t_changeTime = datetime.now()
        f_serverTime = time.time()
        o_newEvent = { 'f_serverTime': f_serverTime, 'i_id': 0, 'event': o_event }
        with self.l_eventLock:
            # set the id for this new event
            a_eventIds = self.getEventIds()
            i_newId = max(0, self.i_nextEventId)
            if (len(a_eventIds) > 0):
                i_newId = max(  i_newId,  max(a_eventIds) + 1  )
            o_newEvent['i_id'] = i_newId

            # append this event and limit the number of events kept in memory
            self.a_events.append(o_newEvent)
            sorted(self.a_events, key=lambda event: event['f_serverTime']) # sort by server time
            while (len(self.a_events) > 100):
                self.a_events.remove(self.a_events[0])
        return o_newEvent

    def updateLatestEventId(self, i_eventId):
        a_eventIds = self.getEventIds()
        i_maxEventId = max(self.i_nextEventId-1, i_eventId)
        if (len(a_eventIds) > 0):
            i_maxEventId = max(i_maxEventId, max(a_eventIds))
        self.i_nextEventId = i_maxEventId + 1

# Multithreaded Python server : TCP Server Socket Thread Pool
class ClientThread(Thread):
 
    def __init__(self, conn, ip, port):
        Thread.__init__(self)
        if (ip != "127.0.0.1"):
            raise Exception()
        self.conn = conn
        self.ip = ip
        self.port = port
        self.b_abort = False
        self.s_roomCode = ""
        self.a_latestEvents = []
        self.t_createTime = datetime.now()
        self.l_abortLock = threading.RLock()
        print "[+] New client socket thread started for " + ip + ":" + str(port)

    def __del__(self):
        try:
            self.tryRemove()
            #print("[-] Client connection closed")
        finally:
            if (self.conn != None):
                self.conn.close()
                self.conn = None

    def removeMe(self):
        with self.l_abortLock:
            self.b_abort = True
        self.tryRemove()
        o_clientKiller1.appendClient(self)
        if (self in a_threads):
            a_threads.remove(self)

    def tryRemove(self, s_roomCode = None):
        if (s_roomCode is None):
            if (self.s_roomCode == None):
                return
            s_roomCode = self.s_roomCode;
        try:
            room = Room.getRoom(s_roomCode, False)
            if (room != None):
                room.removeClient(self)
            if (s_roomCode == self.s_roomCode):
                self.s_roomCode = None
        except Exception as e:
            pass

    def checkTimeKill(self, s_roomCode, i_timeoutSecs):
        # make sure I'm in the right room
        if (s_roomCode != self.s_roomCode):
            # print(">>>>>>>>>>>>>>>>>>>")
            self.tryRemove(s_roomCode)
            room = Room.getRoom(self.s_roomCode, True)
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
        o_roomLocal = Room.getRoom(s_roomCode, False)
        o_roomGlobal = Room.getRoom("", True)
        if (o_roomLocal != None):
            for client in o_roomLocal.getClientsListCopy():
                client.checkTimeKill(s_roomCode, i_timeoutSecs)

        # run a similar check to see if the room needs to be killed
        o_roomGlobal.checkTimeKillAll()

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
            i_expected = -1
            ret = ""
            count = 0

            # receive data until there is no more data to be recieved
            while ((t_now - t_prev).total_seconds() < i_waitTime):
                try:
                    part = self.conn.recv(1, socket.MSG_DONTWAIT)
                except Exception as e2:
                    if not ("Resource temporarily unavailable" in str(e2)): # nothing to be read
                        raise e2
                    else:
                        part = ""
                count = len(part)

                # check if we've received everything
                if (i_expected > -1 and len(ret) == i_expected+10):
                    ret = ret[10:]
                    break
                # check if we've received anything
                if (count > 0):
                    
                    # append this part to the return value
                    ret += part
                    t_prev = t_now

                    # If we've received part of a message, then we know the rest
                    # of the message will be coming in much less than CLIENT_LIFETIME seconds.
                    i_waitTime = 0.1

                    # check if we know how long the string is
                    if (len(ret) >= 10):
                        i_expected = int(ret[:10])
                # nothing to be read right now
                else:
                    time.sleep(0.01)

                t_now = datetime.now()
                with self.l_abortLock:
                    if (self.b_abort):
                        break

            # done receiving a message
            return ret

        except Exception as e:
            print("exception e = " + str(e))
            return default

    def trySend(self, value):
        try:
            # try to send the message
            s_ret = json.dumps(value)
            s_ret = str(len(s_ret)).ljust(10, ' ') + s_ret
            self.conn.send(s_ret)
            return True
        except Exception as e:
            return False

    def getLatestEvents(self):
        room = Room.getRoom(self.s_roomCode, True)
        a_latestEvents = room.getLatestEvents()
        b_ret = self.trySend(a_latestEvents)
        return b_ret

    def getEventById(self, eventId):
        room = Room.getRoom(self.s_roomCode, True)
        o_event = room.getEventById(eventId)
        b_ret = self.trySend(a_latestEvents)
        return b_ret

    def checkLatestEvents(self):
        room = Room.getRoom(self.s_roomCode, True)

        # check for events that I am missing
        missingEvent = room.getMissingEvent(self.a_latestEvents)
        if (missingEvent != None):
            # found a missing event, send it back to the PHP client
            b_ret = self.trySend(missingEvent)
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
        room = Room.getRoom(self.s_roomCode, True)
        self.checkTimeKillAll(self.s_roomCode, CLIENT_LIFETIME)
        room.appendClient(self)
        return room
 
    def updateLatestEventIds(self, a_latestEventIds):
        self.a_latestEvents = a_latestEventIds
        if (len(self.a_latestEvents) > 0):
            eventId = max(self.a_latestEvents)
            room = Room.getRoom(self.s_roomCode, True)
            room.updateLatestEventId(eventId)

    def run(self):
        while True:
            if not self.checkConn():
                break

            ready = select.select([self.conn], [], [], 1)
            b_stop = False
            if ready[0]:
                s_data = self.tryRecv()
                # print("s_data " + s_data)
                with self.l_abortLock:
                    if (self.b_abort):
                        break

                s_data = s_data.strip()
                if (len(s_data) <= 0):
                    continue

                print("[:] Received message \"" + s_data + "\" from client")
                if (s_data.startswith("disconnect")):
                    b_stop = True

                elif (s_data.startswith("getLatestEvents ")):
                    self.tryRemove()
                    a_data = json.loads(s_data[len("getLatestEvents "):])
                    self.setRoomCode(a_data['roomCode'])
                    self.getLatestEvents()

                elif (s_data.startswith("getEventById ")):
                    self.tryRemove()
                    a_data = json.loads(s_data[len("getEventById "):])
                    self.setRoomCode(a_data['roomCode'])
                    self.getEventById(int(a_data['eventId']))

                elif (s_data.startswith("subscribe ")):
                    self.tryRemove()
                    a_data = json.loads(s_data[len("subscribe "):])
                    self.setRoomCode(a_data['roomCode'])
                    self.updateLatestEventIds(a_data['latestEvents'])
                    self.checkLatestEvents()
                
                elif (s_data.startswith("push ")):
                    a_data = json.loads(s_data[len("push "):])
                    room = self.setRoomCode(a_data['roomCode'])
                    o_newEvent = room.appendEvent(a_data['event'])
                    a_clients = room.getClientsListCopy();
                    for client in a_clients:
                        client.pushEvent(o_newEvent)
                    self.trySend(str(len(a_clients)));
            else:
                time.sleep(0.1)
            
            with self.l_abortLock:
                if b_stop or self.b_abort:
                    break

        # This client has timed out or is otherwise done.
        # Remove myself from my room and give myself 1 second before getting deleted.
        self.removeMe()

class ClientKiller():

    def __init__(self, o_clientKillerNext = None):
        self.a_clients = []
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

print("server listening at " + TCP_IP + ":" + str(TCP_PORT) + " (" + str(datetime.now()) + ")")
while True:
    #print("server listening at " + TCP_IP + ":" + str(TCP_PORT) + " (" + str(datetime.now()) + ")")

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
            Room.getRoom("", True).appendClient(newthread)
            newthread.start()
            newthread.checkTimeKillAll("", CLIENT_LIFETIME)
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