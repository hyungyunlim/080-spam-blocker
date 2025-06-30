
from flask import Flask, jsonify
import psutil
import subprocess

app = Flask(__name__)

def get_cpu_temperature():
    try:
        # Read temperature from the thermal zone
        with open("/sys/class/thermal/thermal_zone0/temp", "r") as f:
            temp = int(f.read()) / 1000.0
        return round(temp, 1)
    except FileNotFoundError:
        # Fallback for other systems or if the file doesn't exist
        return None
    except Exception as e:
        print(f"Could not read temperature: {e}")
        return None

@app.route('/status')
def system_status():
    cpu_temp = get_cpu_temperature()
    cpu_usage = psutil.cpu_percent(interval=1)
    memory_info = psutil.virtual_memory()
    disk_info = psutil.disk_usage('/')

    status = {
        "cpu_temperature": cpu_temp,
        "cpu_usage": cpu_usage,
        "memory": {
            "total": memory_info.total,
            "available": memory_info.available,
            "percent": memory_info.percent,
            "used": memory_info.used,
            "free": memory_info.free
        },
        "disk": {
            "total": disk_info.total,
            "used": disk_info.used,
            "free": disk_info.free,
            "percent": disk_info.percent
        }
    }
    return jsonify(status)

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
