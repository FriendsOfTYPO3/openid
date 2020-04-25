# OpenID support for TYPO3 CMS

This extension provides OpenID support for TYPO3 CMS. It is licensed under the same license as the TYPO3 CMS.

## Support

Documentation is available [here](https://docs.typo3.org/p/friendsoftypo3/openid/8.1/en-us).

If you suspect there is a bug, feel free to add the issue to the [issue tracker](https://github.com/FriendsOfTYPO3/openid/issues) on GitHub.

## Troubleshooting

### Login fails since TYPO3 8.7.31 and 9.5.14

These versions of TYPO3 [introduced](https://typo3.org/article/typo3-9514-and-8731-maintenance-releases-published) a SameSite cookies support, which broke OpenID authentication. The fix is to make sure that you have the following in your `web/typo3conf/LocalConfiguration.php`:

```php
return [
    'BE' => [
        'cookieSameSite' => 'lax',
        ...
```
