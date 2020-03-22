# This server acts as the go-between in many php tasks that are running on behalf of long-poll clients.

import socket
from threading import Thread
from SocketServer import ThreadingMixIn
import select
import json
from datetime import datetime

TCP_IP = '127.0.0.1'
TCP_PORT = 12345
clients = { "": [] }

# Multithreaded Python server : TCP Server Socket Thread Pool
class ClientThread(Thread):
    conn = None
    ip = "127.0.0.1"
    port = 0
    camera = ""
    s_last_data = None
    i_last_message_idx = -1;
    t_create_time = None
 
    def __init__(self,conn,ip,port,camera=""):
        Thread.__init__(self)
        if (ip != "127.0.0.1"):
            raise Exception()
        self.conn = conn
        self.ip = ip
        self.port = port
        self.camera = camera
        self.s_last_data = None
        self.i_last_message_idx = -1
        self.t_create_time = datetime.now()
        # print "[+] New server socket thread started for " + ip + ":" + str(port)

    def __del__(self):
        self.conn.close()
        self.tryRemove()
        # print("[-] Client connection closed")

    def tryRemove(self, s_camera = None):
        if (s_camera is None):
            s_camera = self.camera;
        try:
            clients[s_camera].remove(self)
        except Exception as e:
            pass

    def checkTimeKill(self, s_camera, i_timeoutSecs):
        if (s_camera != self.camera):
            # print(">>>>>>>>>>>>>>>>>>>")
            print("client camera " + self.camera + " != " + s_camera)
            self.tryRemove(s_camera)
            if not self in clients[self.camera]:
                clients[self.camera].append(self)
        t_delta = datetime.now() - self.t_create_time
        if (t_delta.total_seconds() > i_timeoutSecs):
            self.__del__()

    def checkTimeKillAll(self, s_camera, i_timeoutSecs):
        for other in clients[s_camera]:
            other.checkTimeKill(s_camera, i_timeoutSecs)

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

    def trySend(self, msg):
        try:
            self.conn.send(msg)
            return True
        except Exception as e:
            return False
 
    def run(self):
        while True:
            if not self.checkConn():
                break
            ready = select.select([self.conn], [], [], 1)
            if ready[0]:
                data = self.tryRecv()
                a_data = data.split("\n")
                b_stop = False
                for s_data in a_data:
                    s_data = s_data.strip()
                    if (len(s_data) <= 0):
                        continue
                    # print("[:] Received message \"" + s_data + "\" from client")
                    if (s_data.startswith("disconnect")):
                        b_stop = True
                        break
                    if (s_data.startswith("subscribe ")):
                        self.tryRemove()
                        a_data = json.loads(s_data[len("subscribe "):])
                        self.camera = a_data['camera']
                        # print("b.4: " + str(clients))
                        if not (self.camera in clients):
                            clients[self.camera] = []
                        elif (len(clients[self.camera]) > 0):
                            first_client = clients[self.camera][0]
                            if (first_client.i_last_message_idx > a_data['message_idx'] and first_client.s_last_data != None):
                                self.trySend(clients[self.camera][0].s_last_data)
                        self.checkTimeKillAll(self.camera, 70)
                        clients[self.camera].append(self)
                        continue
                    a_data = json.loads(s_data)
                    self.s_last_data = s_data
                    self.i_last_message_idx = a_data['message_idx']
                    for otherClient in clients[self.camera]:
                        if (otherClient != self):
                            otherClient.s_last_data = self.s_last_data
                            otherClient.i_last_message_idx = self.i_last_message_idx
                            if not otherClient.trySend(s_data):
                                otherClient.__del__()
                if b_stop:
                    break
        self.__del__()

# Multithreaded Python server : TCP Server Socket Program Stub
tcpServer = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
tcpServer.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
tcpServer.bind((TCP_IP, TCP_PORT))
 
while True:
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
            newthread.checkTimeKillAll("", 70)
            clients[""].append(newthread)
        except Exception as e:
            print "Bad ip \"" + str(ip) + "\", port \"" + str(port) + "\", or camera name \"\": " + str(e)
    except KeyboardInterrupt:
        if conn:
            conn.close()
        break

for camName in clients:
    for t in clients[camName]:
        t.join()