import serial,time, os.path

def setup_serial():
        ser=serial.Serial('/dev/ttyUSB0',115200,timeout=1)
        while(ser.read()!= 'a'):
                pass
        ser.flushInput()
        ser.write('a')
        return ser

#ser = setup_serial()
print "serial setup"
blink = True
file = "/home/pi/pour.bool")
while(1):
	if(os.path.isfile(file)):
		with open(file) as f:
			amount = f.readline()
        	ser.write(amount)
		os.remove(file)
	if(input == "quit" or input == "q"):
                break

ser.close()
