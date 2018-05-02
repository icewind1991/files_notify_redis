# files_notify_redis

Process filesystem change notifications pushed to redis

This app adds support for handling filesystem notifications for local storage backends that are pushed to a redis list.

## Usage

This app depends on a separate program to push filesystem notifications into redis in the following format

- `write|$path`
- `remame|$from|$to`
- `remove|$path`

To a list in redis.

An example program to push the filesystem notifications into redis is [`notify-redis`](https://github.com/icewind1991/notify-redis)

To process the notifications run the following `occ` command

```
occ files_notify_redis:primary [-v] <list>
```

## Requirements

The app currently has the following requirements on the setup

- User's home directories are in the default location of /path-to-data-dir/$user/files
