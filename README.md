common
======

This is my personal framework that I use for most of my websites. The 
goal is not to make a huge framework like Symfony, but to have something 
faster and easier to modify. Therefore, I'm not aiming to make people 
use my framework, but to allow them to check out the source code and use
some of it for their own projects.

Features
======

Here are some features that are supported:

* easy multi website capabilities
* simple overall architecture for better performance
* simple MySQL interactions
* simple routing
* integrates the Twig template engine
* MVC architecture
* abstract authentication system
* admin panel for all applications

The whole framework is highly inspired by Symfony, while staying a lot 
simpler and less powerful.

Live examples
======

As I designed this framework for my specific needs, I use it every day
for my games, which you can find at http://games.remvst.com/

Install
======

If you'd like to quickly try the framework, you will need to create 
several folders: 

* reports
* cache
* logs
* config 
* apps

The apps folder will store your application-specific code. Each 
application will need a strict folder hierarchy.

For instance, if you wish to create a new application called MyWebsite,
you could start with this folder hierarchy:

* apps/
	* MyWebsite/
		* Controller/
			* MyWebsiteController.php
		* Data/
		* Routing/
			* MyWebsiteRouteur.php
		* Resource/
			* config/
				* config.ini
				* config-dev.ini
				* config-prod.ini
			* views/
		* MyWebsiteApplication.php

Once you have this file hierarchy, with empty classes, you can start
your application by including the includes.php file, and use the 
init_app() function.

I would highly recommend that you carefully read the Application,
Controller and Router classes to better understand how the whole 
system works.