#
# Table structure for table 'tx_realurl_cache'
#
CREATE TABLE tx_realurl_cache (
	tstamp int(11) DEFAULT '0' NOT NULL,
	mpvar tinytext NOT NULL,
	workspace int(11) DEFAULT '0' NOT NULL,
	rootpid int(11) DEFAULT '0' NOT NULL,
	languageid int(11) DEFAULT '0' NOT NULL,
	pageid int(11) DEFAULT '0' NOT NULL,
	path text NOT NULL,
	dirty tinyint(3) DEFAULT '0' NOT NULL,

	PRIMARY KEY (pageid,workspace,rootpid,languageid),
	KEY `path_k` (path(100)),
	KEY `path_branch_k` (rootpid,path(100)),
	KEY `ws_lang_k` (workspace,languageid)
) ENGINE=InnoDB;

#
# Table structure for table 'tx_realurl_cachehistory'
#
CREATE TABLE tx_realurl_cachehistory (
	uid int(11) NOT NULL auto_increment,
	tstamp int(11) DEFAULT '0' NOT NULL,
	mpvar tinytext NOT NULL,
	workspace int(11) DEFAULT '0' NOT NULL,
	rootpid int(11) DEFAULT '0' NOT NULL,
	languageid int(11) DEFAULT '0' NOT NULL,
	pageid int(11) DEFAULT '0' NOT NULL,
	path text NOT NULL,

	PRIMARY KEY (uid),
	KEY `path_k` (path(100)),
	KEY `path_branch_k` (rootpid,path(100)),
	KEY `ws_lang_k` (workspace,languageid)
) ENGINE=InnoDB;

#
# Table structure for table 'tx_realurl_uniqalias'
#
CREATE TABLE tx_realurl_uniqalias (
	uid int(11) NOT NULL auto_increment,
	tstamp int(11) DEFAULT '0' NOT NULL,
	tablename varchar(255) DEFAULT '' NOT NULL,
	field_alias varchar(255) DEFAULT '' NOT NULL,
	field_id varchar(60) DEFAULT '' NOT NULL,
	value_alias varchar(255) DEFAULT '' NOT NULL,
	value_id int(11) DEFAULT '0' NOT NULL,
	lang int(11) DEFAULT '0' NOT NULL,
	expire int(11) DEFAULT '0' NOT NULL,
	rootpage_id int(11) DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid),
	KEY tablename (tablename),
	KEY bk_realurl01 (rootpage_id, field_alias(20),field_id,value_id,lang,expire),
	KEY bk_realurl02 (rootpage_id, tablename(32),field_alias(20),field_id,value_alias(20),expire)
);

#
# Table structure for table 'tx_realurl_chashcache'
#
CREATE TABLE tx_realurl_chashcache (
	spurl_hash char(32) DEFAULT '' NOT NULL,
	chash_string varchar(32) DEFAULT '' NOT NULL,
	spurl_string text,

	PRIMARY KEY (spurl_hash),
	KEY chash_string (chash_string)
) ENGINE=InnoDB;

#
# Table structure for table 'tx_realurl_errorlog'
#
CREATE TABLE tx_realurl_errorlog (
	url_hash int(11) DEFAULT '0' NOT NULL,
	url text NOT NULL,
	error text NOT NULL,
	last_referer text NOT NULL,
	counter int(11) DEFAULT '0' NOT NULL,
	cr_date int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	rootpage_id int(11) DEFAULT '0' NOT NULL,

	PRIMARY KEY (url_hash,rootpage_id),
	KEY counter (counter,tstamp)
);

#
# Modifying pages table
#
CREATE TABLE pages (
	tx_realurl_pathsegment varchar(255) DEFAULT '' NOT NULL,
	tx_realurl_pathoverride int(1) DEFAULT '0' NOT NULL,
	tx_realurl_exclude int(1) DEFAULT '0' NOT NULL,
	tx_realurl_nocache int(1) DEFAULT '0' NOT NULL
);

#
# Modifying pages_language_overlay table
#
CREATE TABLE pages_language_overlay (
	tx_realurl_pathsegment varchar(255) DEFAULT '' NOT NULL,
	tx_realurl_pathoverride int(1) DEFAULT '0' NOT NULL,
	tx_realurl_exclude int(1) DEFAULT '0' NOT NULL
);

#
# Modifying sys_domain table
#
CREATE TABLE sys_domain (
	KEY tx_realurl (domainName,hidden)
);

#
# Modifying sys_template table
#
CREATE TABLE sys_template (
	KEY tx_realurl (root,hidden)
);
