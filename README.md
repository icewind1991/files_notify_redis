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

## Notify paths

In order to correctly handle a change notification the app needs to translate between the path retrieved from redis and the path within the Nextcloud virtual file system.

By default the app assumes that the path from redis is in the format of `/path-to-data-dir/$user/files/$path`.

There are 2 ways to tweak this mapping.

1. Using the `--prefix` option if the redis path start with a different prefix then the Nextcloud data directory.
2. Using the `--format` option if the redis paths don't follow the same structure as the app assumes.

### Example

[vfs-notify-redis](https://github.com/icewind1991/samba_vfs_notify_redis) configured to log writes to `[homes]` shares will log paths in the format `/home/$user/$path` to the `notify` list.

You can configure this app to handle those path using the following options.

```
occ files_notify_redis:primary -v --prefix '/home' --format '/$user/$path' notify
```
