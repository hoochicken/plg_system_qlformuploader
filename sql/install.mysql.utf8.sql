CREATE TABLE IF NOT EXISTS `#__qlformuploader_logs` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `created` datetime NOT NULL,
  `fieldname` varchar(255) NOT NULL,
  `tmp_name` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `filesize` int(20) NOT NULL,
  `filetype` varchar(20) NOT NULL,
  `filedestination` varchar(255) NOT NULL,
  `error_upload_server` tinyint(4) NOT NULL,
  `error_upload_file_check` tinyint(1) NOT NULL,
  `error_upload_file_check_msg` varchar(255) NOT NULL,
  `user_id` int(10) NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `module_id` int(5) NOT NULL,
  `module_title` varchar(255) NOT NULL,
  `module_params` varchar(10000) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=11 ;