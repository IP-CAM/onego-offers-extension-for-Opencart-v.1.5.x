OneGo extension for Opencart
v.0.9.4

Contents of this document:
1. Extension overview
2. Installation instructions
3. Changelog
4. License and disclaimer

------------------------------------------------------------------------------------------------------------------------
1. Overview

Onego.com allows merchants to create, manage, publish and analyze offers, rewards and
reloadable gift cards. This extension integrates your eShop with OneGo, allowing
customers to use offers, accrue rewards, use gift card balances and allows you to track use.

For more information on this extension and a demo visit: http://developers.onego.com/eshop/integrations/opencart
For more information on OneGo loyalty system visit: http://business.onego.com

------------------------------------------------------------------------------------------------------------------------
2. Installation

2.1. Requirements

This extension is tested and compatible with Opencart versions 1.5.0, 1.5.1, 1.5.2, 1.5.3 and 1.5.4.
VQMOD extension is required for easier installation, but extension can also be used without it -
you will need to modify Opencart files manually, though.
PHP CURL extension must be installed on the server.

2.2. Installation

- Simply copy files from src/ directory to root directory of your Opencart installation;
- Open admin interface, go to Extensions > Order Totals, click "Install" for this extension;
- Open extension configuration page to configure it for your OneGo business account;
- Copy template files to your theme directory and modify them if needed.

We highly recommend to test the extension with your Opencart installation before making it public,
especially if you have a highly customized e-shop - this extension may be incompatible with other
extensions.

To install extension without VQMOD, you will have to open vqmod/xml/onego_benefits.xml file and apply
changes configured there to your code manually.

------------------------------------------------------------------------------------------------------------------------
3. Changelog

v.0.9.4 - Dec 1, 2012
  * Initial release

------------------------------------------------------------------------------------------------------------------------
4. Licence and disclaimer

The MIT License (MIT)
Copyright © 2012 OneGo Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy of this software
and associated documentation files (the “Software”), to deal in the Software without restriction,
including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense,
and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do
so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or
substantial portions of the Software.

THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING
BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF
OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.