import paramiko

HOST = "212.227.215.235"
USER = "modali"
PASS = "Hp9conDIhfVuBtxY"

def upload_and_run():
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=PASS, timeout=15)
    sftp = ssh.open_sftp()

    local_files = [
        (r"C:\Users\dali\streamhub\setup_db.php", "/public_html/streamhub/setup_db.php"),
        (r"C:\Users\dali\streamhub\sql\schema.sql", "/public_html/streamhub/sql/schema.sql"),
    ]
    for local, remote in local_files:
        sftp.put(local, remote)
        print(f"Uploaded: {remote}")

    sftp.close()

    stdin, stdout, stderr = ssh.exec_command("php /public_html/streamhub/setup_db.php")
    print(stdout.read().decode())
    err = stderr.read().decode()
    if err:
        print("STDERR:", err)

    ssh.close()

if __name__ == "__main__":
    upload_and_run()
