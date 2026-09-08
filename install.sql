CREATE TABLE IF NOT EXISTS `{#}ai_moderator_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(32) NOT NULL DEFAULT 'comment',
  `subject_id` int(11) unsigned NOT NULL DEFAULT '0',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0',
  `action` varchar(16) NOT NULL DEFAULT 'log',
  `score` float NOT NULL DEFAULT '0',
  `category` varchar(16) NOT NULL DEFAULT 'normal',
  `reason` varchar(500) DEFAULT NULL,
  `text_hash` char(32) DEFAULT NULL,
  `data` text,
  `date_pub` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `date_pub` (`date_pub`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;