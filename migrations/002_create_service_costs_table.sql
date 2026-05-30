CREATE TABLE `service_costs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_name` varchar(255) NOT NULL,
  `service_size` varchar(50) DEFAULT NULL,
  `cost` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_name_size` (`service_name`,`service_size`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;