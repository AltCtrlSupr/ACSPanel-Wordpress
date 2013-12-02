ACSPanelWordpressBundle
=======================

Support to admin a Wordpress farm for the ACSPanel
This solution explores the concept of [Wordpress Multitenancy][1] to create a Wordpress farm using the same Wordpress core files to serve how many blogs you wish, each Blog has their own content folder.

How it works
------------

It creates a new table in database to create relation between one hosting and a database user. From here the wordpress farm knows the connection parameters to create the new blog.

[1]: http://jason.pureconcepts.net/2012/08/wordpress-multitenancy/
