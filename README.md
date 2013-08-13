ZendServer_Joomla_Adapter
=========================

Zend Server cache adapters for Joomla! 3.x

Installation
------------

In order to install the Zend Server cache adapters for Joomla! 3.x you can
execute the `install.php` script as follow:

```bash
php install.php <path-to-Joomla>
```

where <path-to-Joomla> is the path the root folder of the Joomla! 3.x installation.
The script will copy the `/joomla3/*.php` files in the `/libraries/joomla/cache/storage`
folder and add the JLIB_FORM_VALUE_CACHE_ZENDSERVER value to the 
`/administrator/language/en-GB/en-GB.lib_joomla.ini` file of Joomla!.

