import paramiko

HOST = "212.227.215.235"
USER = "modali"
PASS = "Hp9conDIhfVuBtxY"

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, username=USER, password=PASS, timeout=15)
sftp = ssh.open_sftp()
sftp.put(r"C:\Users\dali\streamhub\test_mysql.php", "/public_html/streamhub/test_mysql.php")
sftp.close()
ssh.close()
print("Uploaded test_mysql.php")
