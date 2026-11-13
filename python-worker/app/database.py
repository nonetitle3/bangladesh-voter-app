import pymysql
from pymysql.cursors import DictCursor
from .config import settings

def connect():
    missing = [name for name, value in {
        "DB_HOST": settings.db_host, "DB_DATABASE": settings.db_name,
        "DB_USERNAME": settings.db_user, "DB_PASSWORD": settings.db_password,
    }.items() if not value]
    if missing:
        raise RuntimeError("Missing worker database configuration: " + ", ".join(missing))
    return pymysql.connect(
        host=settings.db_host, port=settings.db_port, user=settings.db_user,
        password=settings.db_password, database=settings.db_name,
        charset=settings.db_charset, cursorclass=DictCursor,
        connect_timeout=settings.db_timeout, autocommit=False,
    )
