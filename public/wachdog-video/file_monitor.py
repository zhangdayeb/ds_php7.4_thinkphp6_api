# -*- coding: utf-8 -*-
# file_monitor.py
import os
import time
from datetime import datetime
import hashlib
from watchdog.observers import Observer
from watchdog.events import FileSystemEventHandler
import mysql.connector
import mimetypes
# import subprocess


# 数据库配置信息
db_config = {
    'user': 'root',
    'password': '123456',
    'host': 'localhost',
    'database': 'dashang',
    'raise_on_warnings': True,
    'port': '8889'
}

# 连接到 MySQL 数据库
db = mysql.connector.connect(**db_config)
cursor = db.cursor()
# CREATE TABLE `video_temp` (
#   `id` int(10) NOT NULL AUTO_INCREMENT,
#   `filename` varchar(255) DEFAULT NULL COMMENT '文件名称',
#   `path` varchar(255) DEFAULT NULL COMMENT '文件路径',
#   `filesize` double(12,2) DEFAULT NULL COMMENT '文件大小',
#   `hash` varchar(255) DEFAULT NULL COMMENT '文件 hash',
#   `image` varchar(255) DEFAULT NULL COMMENT '封面',
#   `createtime` datetime DEFAULT NULL,
#   `updatetime` datetime DEFAULT NULL,
#   `is_sys` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否同步：0=未同步，1=已同步',
#   `remark` varchar(1000) DEFAULT NULL COMMENT '备注',
#   PRIMARY KEY (`id`)
# ) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='批量上传文件处理临时表';

def file_hash(filepath):
    """计算文件的 SHA256 哈希值"""
    hash_sha256 = hashlib.sha256()
    with open(filepath, "rb") as f:
        for chunk in iter(lambda: f.read(4096), b""):
            hash_sha256.update(chunk)
    return hash_sha256.hexdigest()

def is_video_mime(file_path):
    mime_type, _ = mimetypes.guess_type(file_path)
    video_types = ['video/mp4', 'video/avi', 'video/quicktime', 'video/x-matroska', 'video/webm']
    return mime_type in video_types

# def get_video_frame(video_path):
#     """使用 FFmpeg 获取视频的第一帧并保存为图片"""
#     frame_filename = os.path.splitext(video_path)[0] + ".jpg"
#     cmd = [
#         'ffmpeg',
#         '-i', video_path,  # 视频文件路径
#         '-vframes', '1',    # 提取1帧
#         '-q:v', '2',        # 质量设置
#         '-f', 'jpeg',      # 输出格式
#         frame_filename     # 输出文件名
#     ]
#     subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
#     return frame_filename


class MyHandler(FileSystemEventHandler):
    def on_created(self, event):
        if not event.is_directory:
            self.process_event(event)

    def on_modified(self, event):
        if not event.is_directory:
            self.process_event(event)

    def process_event(self, event):
        filepath = event.src_path
        filename = os.path.basename(filepath)
        path = os.path.dirname(filepath) + "/" + filename
        filesize = os.path.getsize(filepath)
        createtime = datetime.fromtimestamp(os.path.getctime(filepath))
        updatetime = datetime.now()
        file_hash_value = file_hash(filepath)

        # 检查文件是否为视频文件
        if is_video_mime(path) == False:
            print(f"==== {filename} is not a video file.")
            return

        # 检查数据库中是否已存在该哈希值的记录
        cursor.execute('SELECT hash FROM video_temp WHERE hash = %s', (file_hash_value,))
        existing_record = cursor.fetchone()
        if not existing_record:
            # image_path = get_video_frame(path)
            # 插入记录到数据库
            sql = '''
                INSERT INTO video_temp (filename, path, filesize, createtime, updatetime, hash)
                VALUES (%s, %s, %s, %s, %s, %s)
            '''
            values = (filename, path, filesize, createtime, updatetime, file_hash_value)
            cursor.execute(sql, values)
            db.commit()
            print(f"Record for {filename} saved to database.")
        else:
            print(f"Record for {filename} already exists in database.")

if __name__ == "__main__":
    path = "videos"  # 监控的目录路径
    event_handler = MyHandler()
    observer = Observer()
    observer.schedule(event_handler, path, recursive=True)
    observer.start()

    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        observer.stop()
    observer.join()

    # 关闭数据库连接
    cursor.close()
    db.close()