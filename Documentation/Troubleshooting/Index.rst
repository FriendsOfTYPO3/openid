.. include:: /Includes.rst.txt

===============
Troubleshooting
===============

Login fails since TYPO3 8.7.31 and 9.5.14
=========================================

These versions of TYPO3
`introduced <https://typo3.org/article/typo3-9514-and-8731-maintenance-releases-published>`__
a SameSite cookies support, which broke OpenID authentication. The fix is to
make sure that you have the following in your :file:`web/typo3conf/LocalConfiguration.php`:

.. code-block:: none

   return [
       'BE' => [
           'cookieSameSite' => 'lax',
           ...
