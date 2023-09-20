# Story

*(Everything talked here is for `Magento 2.4`. See **Requirements Section**.)*

This module, which provides 2 features, is designed as a dependency for making other modules/themes.

1. During deployment, Magento scans static `.js` and `.html` files and picks up translatable strings from them.\
Then Magento generates a `js-traslation.json` file.\
This file is a dictionary file used for frontend translation,\
For example: `<!-- ko i18n: 'Translate Me!' --><!-- /ko -->`\
Unfortunately, translatable strings in `.phtml(and .php)` files are not picked up by Magento(for some reasons).\
If `$.mage.__('Some Text')` is in `.phtml` files, it won't be translated.\
This module can force extra data from a separate file(i.e., [module or theme root]/i18n/**Js**/[locale].csv) to go to `js-traslation.json`.

2. Magento loads translation data in the order below:
```
 |  Module translation: [module root]/i18n/[locale].csv
 |  Language Pack translation
 |  Theme translation: [theme root]/i18n/[locale].csv
\|/ Database translation
```
Then these translation data are merged and the merge rule is **"LAST ONE WINS"**, which means Database translation overrides everything else.\
However, In some cases, especially when doing local development to translate very general phrases in specific business field, the Module creator wants to override Language Pack and Theme translations without modifying those third party packages.\
This module adds extra locations for translation files(CSV), so the loading order becomes:

>&nbsp;|&nbsp; Module translation: [module root]/i18n/[locale].csv
> 
>&nbsp;|&nbsp; Language Pack translation
> 
>&nbsp;|&nbsp; Theme translation: [theme root]/i18n/[locale].csv
> 
>&nbsp;|&nbsp; Database translation
> 
>&nbsp;|&nbsp; Extra Module translation: [module root]/i18n/**i18n**/[locale].csv
> 
>\\|/ Extra Theme translation: [theme root]/i18n/**i18n**/[locale].csv(last-one-wins)

This module works under *<ins>Store Emulation</ins>*, too.

# Usage and Example
**1. Append(merge) translation into `js-traslation.json`:**\
Create `<module or theme root>/i18n/Js/<locale>.csv` like a normal translation CSV file, then its content will go to `js-traslation.json`(even the phrase cannot be found as a translatable string in static files).

**2. Override Language Pack or Theme translations:**\
Create [module or theme root]/i18n/**i18n**/[locale].csv like a normal translation CSV file, then it will work.

*Check examples of this module inside the <ins>i18n</ins> folder.*

`i18n/ja_JP.csv`
```
"###Translate Me!###","翻訳して！"
```
`i18n/i18n/ja_JP.csv`
```
"###Translate Me!###","翻訳してください！(Override)"
```
`i18n/Js/ja_JP.csv`
```
"###This phrase will go to js-translation.json anyway.###","このフレーズは必ずjs-translation.jsonに出現する。"
"###This phrase will be overridden.###","このフレーズはオーバーライドされる"
```
`i18n/i18n/Js/ja_JP.csv`
```
"###This phrase will be overridden.###","このフレーズをオーバーライドした"
```

<ins>Output:</ins>

```
###Translate Me!### -> 翻訳してください！(Override)
```

<ins>Output in `js-translation.json`:</ins>

```
###This phrase will go to js-translation.json anyway.### -> このフレーズは必ずjs-translation.jsonに出現する。
###This phrase will be overridden.### -> このフレーズをオーバーライドした
```

# Requirements
**Magento 2.4**

# Installation
**`composer require wubinworks/module-translation`**
