# This file is part of Jeedom.
#
# Jeedom is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
# 
# Jeedom is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License
# along with Jeedom. If not, see <http://www.gnu.org/licenses/>.

from email import message
import logging
import string
import sys
import os
import time
import datetime
import traceback
import re
import signal
from optparse import OptionParser
from os.path import join
import json
import argparse
import websocket
from threading import Thread
import time
import sys

try:
	from jeedom.jeedom import *
except ImportError:
	print("Error: importing module jeedom.jeedom")
	sys.exit(1)

def read_socket():
	global JEEDOM_SOCKET_MESSAGE
	if not JEEDOM_SOCKET_MESSAGE.empty():
		message = json.loads(JEEDOM_SOCKET_MESSAGE.get().decode('utf-8'))
		if message['apikey'] != _apikey:
			logging.error("Invalid apikey from socket : " + str(message))
			return

		if "command" not in message:
			return

		if message["command"] == "getStations":
			logging.info("_stations : " + str(_stations))
			_jeedomCom.send_change_immediate({'type': 'stations', 'stations': str(_stations)})
			return

		if message["command"] == "getDevices":
			logging.info("_devices : " + str(_devices))
			_jeedomCom.send_change_immediate({'type': 'devices', 'devices': str(_devices)})
			return

		if "name" in message and "value" in message:
			jsonMsg = "{\"command\": \"" + message['command'] + "\", \"serialNumber\": \"" + message['serialNumber'] + "\", \"name\": \"" + message['name'] + "\", \"value\": \"" + message['value'] + "\"}"
			logging.debug("json sent : " + jsonMsg)

			_websocket.send(jsonMsg)
			time.sleep(1)
			return

		try:
			jsonMsg = "{\"command\": \"" + message['command'] + "\", \"serialNumber\": \"" + message['serialNumber'] + "\"}"
			logging.debug("json sent : " + jsonMsg)

			_websocket.send(jsonMsg)
			time.sleep(1)
			return
		except Exception as e:
			logging.error("Unable to send Websocket command: " + str(e))
			
		
def listen():
	jeedom_socket.open()
	try:
		while 1:
			time.sleep(0.5)
			read_socket()
	except KeyboardInterrupt:
		shutdown()

def startWebsocket():	
	logging.debug("Starting websocket with the container")
	websocket.enableTrace(False)
	host = "ws://" + _containerip + ":" + _containerport + "/"

	_ws = websocket.WebSocketApp(host,
                                on_message=on_message,
                                on_error=on_error,
                                on_close=on_close)
	_ws.on_open = on_open
	#_ws.run_forever()
	th = threading.Thread(target=_ws.run_forever)
	th.daemon = True
	th.start()

	logging.info("Websocket started")
# ----------------------------------------------------------------------------

def handler(signum=None, frame=None):
	logging.debug("Signal %i caught, exiting..." % int(signum))
	shutdown()

def shutdown():
	logging.debug("Shutdown")
	logging.debug("Removing PID file " + str(_pidfile))
	try:
		os.remove(_pidfile)
	except:
		pass
	try:
		jeedom_socket.close()
	except:
		pass
	try:
		jeedom_serial.close()
	except:
		pass

	# Close websocket
	_websocket.close()

	logging.debug("Exit 0")
	sys.stdout.flush()
	os._exit(0)

# ----------------------------------------------------------------------------

def on_message(ws, msg):
	logging.debug(msg)
	jsonMsg = json.loads(msg)

	if jsonMsg['type'] == 'version':
		parseVersionMessage(jsonMsg)

	if jsonMsg['type'] == 'result':
		parseResultMessage(jsonMsg)
	
	if jsonMsg['type'] == 'event':
		parseEventMessage(jsonMsg)

def on_error(ws, error):
	logging.error(error)

def on_close(ws, close_status_code, close_msg):
	logging.info("Websocket closed")

def on_open(ws):
	global _websocket
	_websocket = ws
	logging.info("Websocket opened")
	def run(*args):
		_websocket.send("{\"command\": \"driver.set_log_level\", \"level\":\"trace\"}") # Set logs
		time.sleep(1)
		_websocket.send("{\"command\": \"start_listening\"}") # start listening events
		time.sleep(1)
		_websocket.send("{\"command\": \"set_api_schema\", \"schemaVersion\": 11}") # Set API schema
		time.sleep(1)
	
	Thread(target=run).start()

def parseResultMessage(msg):
	global _stations
	global _devices
	result = msg['result']

	if "state" in result:
		logging.debug('State message Result received')
		_stations= msg['result']['state']['stations']
		logging.debug(str(_stations))
		_devices = msg['result']['state']['devices']
		logging.debug(str(_devices))
		return
	if "properties" in result:
		logging.debug('Properties message received')
		for prop in result['properties']:
			_jeedomCom.send_change_immediate({'type': 'event', 'subtype': 'properties', 'serialNumber': result['serialNumber'], 'property': prop, 'value':  result['properties'][prop]})

	logging.warning('Unsupported Result message received')

def parseEventMessage(msg):
	evtMsg = msg['event']
	
	if evtMsg['event'] == 'property changed':
		logging.debug('Event property changed received')
		_jeedomCom.send_change_immediate({'type': 'event', 'source': evtMsg['source'], 'serialNumber': evtMsg['serialNumber'], 'property': evtMsg['name'], 'value': evtMsg['value']})
	
def parseVersionMessage(msg):
	logging.debug('Version message received')
# ----------------------------------------------------------------------------

_log_level = "error"
_socket_port = 60600
_socket_host = 'localhost'
_device = 'auto'
_pidfile = '/tmp/demond.pid'
_apikey = ''
_callback = ''
_cycle = 0.3
_containerip = ""
_containerport = 0
_devices = ""

parser = argparse.ArgumentParser(
    description='Desmond Daemon for Jeedom plugin')
parser.add_argument("--loglevel", help="Log Level for the daemon", type=str)
parser.add_argument("--callback", help="Callback", type=str)
parser.add_argument("--apikey", help="Apikey", type=str)
parser.add_argument("--cycle", help="Cycle to send event", type=str)
parser.add_argument("--pid", help="Pid file", type=str)
parser.add_argument("--socketport", help="Port for Zigbee server", type=str)
parser.add_argument("--containerip", help="Container IP", type=str)
parser.add_argument("--containerport", help="Container Port", type=str)
args = parser.parse_args()

if args.loglevel:
    _log_level = args.loglevel
if args.callback:
    _callback = args.callback
if args.apikey:
    _apikey = args.apikey
if args.pid:
    _pidfile = args.pid
if args.cycle:
    _cycle = float(args.cycle)
if args.socketport:
	_socketport = args.socketport
if args.containerip:
	_containerip = args.containerip
if args.containerport:
	_containerport = args.containerport
		
_socket_port = int(_socket_port)

jeedom_utils.set_log_level(_log_level)

logging.info('Start demond')
logging.info('Log level : '+str(_log_level))
logging.info('Socket port : '+str(_socket_port))
logging.info('Socket host : '+str(_socket_host))
logging.info('PID file : '+str(_pidfile))
logging.info('Apikey : '+str(_apikey))
logging.info('Container IP : '+str(_containerip))
logging.info('Container Port : '+str(_containerport))

_ws = websocket

signal.signal(signal.SIGINT, handler)
signal.signal(signal.SIGTERM, handler)	

try:
	jeedom_utils.write_pid(str(_pidfile))
	jeedom_socket = jeedom_socket(port=_socket_port,address=_socket_host)
	_jeedomCom = jeedom_com(_apikey, _callback)

	# Start WebSocket connection with the container
	startWebsocket()
	# Listen data from Jeedom
	listen()
except Exception as e:
	logging.error('Fatal error : '+str(e))
	logging.info(traceback.format_exc())
	shutdown()
