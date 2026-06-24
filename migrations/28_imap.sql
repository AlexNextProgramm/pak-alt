INSERT IGNORE INTO `variable` (`type`, `name`, `value`) VALUES
('imap', 'host', ''),
('imap', 'port', '993'),
('imap', 'username', ''),
('imap', 'password', ''),
('imap', 'encryption', 'ssl'),
('imap', 'verify_ssl', '1'),
('imap', 'folder', '');

UPDATE `variable`
SET `value` = ''
WHERE `type` = 'imap' AND `name` = 'folder';
