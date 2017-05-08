ALTER TABLE subscriptions
ADD COLUMN `secret` varchar(200) DEFAULT '' AFTER `challenge`;
