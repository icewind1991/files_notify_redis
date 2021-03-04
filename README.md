# files_notify_redis

Process filesystem change notifications pushed to redis

This app adds support for handling filesystem notifications for local storage backends that are pushed to a redis list.

## Usage

This app depends on a separate program to push filesystem notifications into redis, the format for these notifications is described [here](https://github.com/icewind1991/nc-fs-events/).

An example program to push the filesystem notifications into redis is [`notify-redis`](https://github.com/icewind1991/notify-redis)

To process the notifications run the following `occ` command

```
occ files_notify_redis:primary [-v] <list>
```

## Notify paths

In order to correctly handle a change notification the app needs to translate between the path retrieved from redis and the path within the Nextcloud virtual file system.

By default, the app assumes that the path from redis is in the format of `/path-to-data-dir/$user/files/$path`.

There are 2 ways to tweak this mapping.

1. Using the `--prefix` option if the redis path start with a different prefix then the Nextcloud data directory.
2. Using the `--format` option if the redis paths don't follow the same structure as the app assumes.

## Multiple redis servers

For scalability, it is possible to set up a system using multiple redis server and/or multiple Nextcloud workers.

Any number of Nextcloud workers can listen to the same redis instance for notifications and multiple redis instances can be used
with at least one Nextcloud worker listening to each redis instance, this way the notify-system can scale horizontally with the
only limitation being the central Nextcloud database instance. 

When a redis server different from the Nextcloud default one should be used, you can pass the `--host`, `--port` and `--password`
options.

### Example

### Using inotify

[notify-redis](https://github.com/icewind1991/notify-redis) set to listen to the data directory of the Nextcloud instance:

```bash
notify-redis /path/to/nextcloud/data redis://localhost notify
```

Then run the `files_notify_redis:primary` provided by this app

```bash
occ files_notify_redis:primary -v notify
```

The `files_notify_redis:primary` command expects all paths to be prefixed by the path to the data directory by default.
In cases where the data directory is in different locations for `notify-redis` and Nextcloud (such as when running the notify command from an NFS server), you can configure the prefix using the `--prefix` option.

```bash
occ files_notify_redis:primary -v --prefix /path/to/data notify
```

### Using the samba plugin

[vfs-notify-redis](https://github.com/icewind1991/samba_vfs_notify_redis) configured to log writes to `[homes]` shares will log paths in the format `/home/$user/$path` to the `notify` list.

You can configure this app to handle those paths using the following options.

```
occ files_notify_redis:primary -v --prefix '/home' --format '/$user/$path' notify
```
