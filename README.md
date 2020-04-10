# hanzi2pinyin

# Purpose

This library is used to convert Chinese characters to Pinyin.

It contains thousands of characters, includes ZHS, ZHT.

# How to use?

Download zip package, and unzip it.

You can change its namespace to be yours.

In you code:

```php
$ch2py = new Ch2py();
print_r($ch2py->getPinYins('汉'));
print_r($ch2py->toString('汉字'));
```
