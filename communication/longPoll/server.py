# This server acts as the go-between in many php tasks that are running on behalf of long-poll clients.

import socket
from threading import Thread
from SocketServer import ThreadingMixIn
import select
import json
from datetime import datetime
import time

TCP_IP = '127.0.0.1'
TCP_PORT = 23456

class Room():
    s_roomCode = ""
    a_clients = []
    a_indexes = []
    t_createTime = None

    def __init__(self, s_roomCode):
        self.s_roomCode = s_roomCode
        self.t_createTime = datetime.now()

    def checkTimeKill(self):
        if (self.s_roomCode == ""):
            return False
        if (len(self.a_clients) == 0):
            t_delta = datetime.now() - self.t_createTime
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

    def getLaterIndex(self, time):
        for index in self.a_indexes:
            if (index.message_time > latestTime):
                return index
        return None

    def appendIndex(self, key, event):
        self.a_indexes.append(event)
        while (len(self.a_indexes) > 100):
            self.a_indexes.remove(self.a_indexes[0])

# Multithreaded Python server : TCP Server Socket Thread Pool
class ClientThread(Thread):
    conn = None
    ip = "127.0.0.1"
    port = 0
    abort = False
    s_roomCode = ""
    a_latestIndexes = []
    t_createTime = None
 
    def __init__(self, conn, ip, port):
        Thread.__init__(self)
        if (ip != "127.0.0.1"):
            raise Exception()
        self.conn = conn
        self.ip = ip
        self.port = port
        self.t_createTime = datetime.now()
        print "[+] New client socket thread started for " + ip + ":" + str(port)

    def __del__(self):
        self.conn.close()
        self.tryRemove()
        print("[-] Client connection closed")
        if (self in a_threads):
            a_threads.remove(self)

    def tryRemove(self, s_roomCode = None):
        if (s_roomCode is None):
            s_roomCode = self.s_roomCode;
        try:
            a_rooms[s_roomCode].a_clients.remove(self)
        except Exception as e:
            pass

    def checkTimeKill(self, s_roomCode, i_timeoutSecs):
        if (s_roomCode != self.s_roomCode):
            # print(">>>>>>>>>>>>>>>>>>>")
            print("client s_roomCode " + self.s_roomCode + " != " + s_roomCode)
            self.tryRemove(s_roomCode)
            if not self in a_rooms[self.s_roomCode].a_clients:
                a_rooms[self.s_roomCode].a_clients.append(self)
        t_delta = datetime.now() - self.t_createTime
        if (t_delta.total_seconds() > i_timeoutSecs):
            self.__del__()

    def checkTimeKillAll(self, s_roomCode, i_timeoutSecs):
        for client in a_rooms[s_roomCode].a_clients:
            client.checkTimeKill(s_roomCode, i_timeoutSecs)
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
            return self.conn.recv(2048)
        except Exception as e:
            return default

    def trySend(self, value):
        try:
            # try to send the message
            self.conn.send(json.dumps(value))
            return True
        except Exception as e:
            return False

    def checkLatestIndexes(self):
        room = a_rooms[self.s_roomCode]

        # find the latest index time from my latest_indexes
        latestTime = 0
        for index in self.a_latestIndexes:
            if (index.message_time > latestTime):
                latestTime = index.message_time

        # check for indexes that have a later time than my latest time
        laterIndex = room.getLaterIndex(latestTime)
        if (laterIndex != None):
            # found a later index, send it back to the PHP client
            return self.trySend(laterIndex)

        return False

    def pushIndex(self, key, event):
        pass
 
    def run(self):
        while True:
            if not self.checkConn():
                break

            ready = select.select([self.conn], [], [], 1)
            b_stop = False
            if ready[0]:
                s_data = self.tryRecv()
                s_data = s_data.strip()
                if (len(s_data) <= 0):
                    continue

                print("[:] Received message \"" + s_data + "\" from client")
                if (s_data.startswith("disconnect")):
                    b_stop = True

                elif (s_data.startswith("subscribe ")):
                    self.tryRemove()
                    a_data = json.loads(s_data[len("subscribe "):])
                    self.s_roomCode = a_data['roomCode']
                    self.a_latestIndexes = a_data['latestIndexes']
                    if not (self.s_roomCode in a_rooms):
                        a_rooms[self.s_roomCode] = Room(self.s_roomCode)
                    room = a_rooms[self.s_roomCode]
                    self.checkTimeKillAll(self.s_roomCode, 2)
                    room.a_clients.append(self)
                    if (self.checkLatestIndexes()):
                        # successfully sent a message, so we know PHP client has disconnected and is no longer listening
                        # remove this instance
                        b_stop = True
                
                elif (s_data.startswith("push ")):
                    room = a_rooms[self.s_roomCode]
                    a_data = json.loads(s_data[len("push "):])
                    room.appendIndex(a_data['key'], a_data)
                    for client in room.a_clients:
                        client.pushIndex(a_data['key'], a_data)
            else:
                time.sleep(50)
                
            if b_stop or self.abort:
                break
        self.__del__()

# Multithreaded Python server : TCP Server Socket Program Stub
tcpServer = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
tcpServer.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
tcpServer.bind((TCP_IP, TCP_PORT))

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
            conn.setblocking(0)
            newthread = ClientThread(conn,ip,port)
            newthread.start()
            newthread.checkTimeKillAll("", 2)
            a_rooms[""].a_clients.append(newthread)
            a_threads.append(newthread)
        except Exception as e:
            print "Bad ip \"" + str(ip) + "\", port \"" + str(port) + "\", or camera name \"\": " + str(e)
    except KeyboardInterrupt:
        if conn:
            conn.close()
        break

for t in a_threads:
    t.abort = True