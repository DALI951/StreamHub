import paramiko
import os
import sys

HOST = "212.227.215.235"
PORT = 22
USER = "modali"
PASS = "Hp9conDIhfVuBtxY"
REMOTE_BASE = "/public_html/streamhub"
LOCAL_BASE = r"C:\Users\dali\streamhub"

SKIP_DIRS = {'.git', 'node_modules', '__pycache__', '.vscode'}
SKIP_FILES = {'upload.py', 'upload_check.py', '.gitignore', 'check_extensions.php', 'README.md'}

def upload_dir(sftp, local_dir, remote_dir):
    for item in os.listdir(local_dir):
        if item in SKIP_FILES or item in SKIP_DIRS:
            continue
        local_path = os.path.join(local_dir, item)
        remote_path = remote_dir + "/" + item.replace("\\", "/")

        if os.path.isdir(local_path):
            try:
                sftp.stat(remote_path)
            except FileNotFoundError:
                print(f"  mkdir: {remote_path}")
                sftp.mkdir(remote_path)
            upload_dir(sftp, local_path, remote_path)
        else:
            print(f"  upload: {remote_path}")
            sftp.put(local_path, remote_path)

def main():
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    print(f"Connecting to {HOST}...")
    ssh.connect(HOST, port=PORT, username=USER, password=PASS, timeout=15)
    sftp = ssh.open_sftp()

    try:
        sftp.stat(REMOTE_BASE)
    except FileNotFoundError:
        print(f"Creating {REMOTE_BASE}")
        sftp.mkdir(REMOTE_BASE)

    print(f"\nUploading {LOCAL_BASE} -> {REMOTE_BASE}")
    upload_dir(sftp, LOCAL_BASE, REMOTE_BASE)
    print("\nDone! Visit: http://modali.powerpme.com/streamhub/")

    sftp.close()
    ssh.close()

if __name__ == "__main__":
    main()
