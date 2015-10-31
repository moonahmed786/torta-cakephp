# Introduction #

Torta-cakePHP has been originally design as an **Torrent Tracker** application that runs on top of the [CakePHP](http://cakephp.org/) Framework. This simple application will allow cake developers to easily incorporate a torrent tracking system to their current installation of [CakePHP](http://cakephp.org/).

Our first step in developing this app was to incorporate the basic "Torrent protocol" communication between the server and the clients. With this simple interface we allow the server to host/track torrent files on the web server, and also provide clients with the necessary information to download the files.

# Installation #

The current version of **Torta** can only be installed in CakePHP 1.2.x. In the .zip file you will find the necessary files/folders.

Please note we have an "upload" function on the app\_controller.php file. DO **NOT** overwrite your original file.

## Database ##

Below you will find the two tables required by our App, which you will need to create in your database.

```
CREATE TABLE `peers` (
  `id` int(100) unsigned NOT NULL auto_increment,
  `torrent_id` int(11) NOT NULL,
  `peer_id` varchar(50) NOT NULL default '',
  `bytes` bigint(20) NOT NULL default '0',
  `ip` varchar(50) NOT NULL default 'error.x',
  `port` smallint(5) NOT NULL default '0',
  `status` enum('leecher','seeder') NOT NULL default 'leecher',
  `lastupdate` int(10) NOT NULL default '0',
  `sequence` int(10) NOT NULL,
  `natuser` enum('N','Y') NOT NULL default 'N',
  `uploaded` bigint(20) NOT NULL default '0',
  `downloaded` bigint(20) NOT NULL default '0',
  `no_peer_id` varchar(40) NOT NULL,
  `compact` varchar(6) NOT NULL,
  `modified` timestamp NULL default NULL,
  `created` timestamp NULL default NULL,
  PRIMARY KEY  (`id`)
);

CREATE TABLE `torrents` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `info_hash` char(40) NOT NULL,
  `url` varchar(250) default NULL,
  `filename` varchar(250) default NULL,
  `info` varchar(250) default NULL,
  `dlbytes` bigint(20) default NULL,
  `seeds` int(10) default NULL,
  `leechers` int(10) default NULL,
  `finished` int(10) default NULL,
  `lastcycle` int(10) default NULL,
  `lastSpeedCycle` int(10) default NULL,
  `Speed` bigint(20) default NULL,
  `filestore` binary(1) default NULL,
  `ftype` varchar(255) default NULL,
  `fsize` int(11) default NULL,
  `path` varchar(500) NOT NULL,
  `name` varchar(255) default NULL,
  `description` longtext,
  PRIMARY KEY  (`id`)
);
```