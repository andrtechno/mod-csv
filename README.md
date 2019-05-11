# mod-csv

Module for PIXELION CMS

[![Latest Stable Version](https://poser.pugx.org/panix/mod-csv/v/stable)](https://packagist.org/packages/panix/mod-csv) [![Total Downloads](https://poser.pugx.org/panix/mod-csv/downloads)](https://packagist.org/packages/panix/mod-csv) [![Monthly Downloads](https://poser.pugx.org/panix/mod-csv/d/monthly)](https://packagist.org/packages/panix/mod-csv) [![Daily Downloads](https://poser.pugx.org/panix/mod-csv/d/daily)](https://packagist.org/packages/panix/mod-csv) [![Latest Unstable Version](https://poser.pugx.org/panix/mod-csv/v/unstable)](https://packagist.org/packages/panix/mod-csv) [![License](https://poser.pugx.org/panix/mod-csv/license)](https://packagist.org/packages/panix/mod-csv)


## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

#### Either run

```
php composer require --prefer-dist panix/mod-csv "*"
```

or add

```
"panix/mod-csv": "*"
```

to the require section of your `composer.json` file.


#### Add to web config.
```
'modules' => [
    'csv' => ['class' => 'panix\mod\csv\Module'],
],
```
