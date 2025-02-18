<p align="center"><img src="./src/icon.svg" width="100" height="100" alt="Spotler Transactional icon"></p>

<h1 align="center">Spotler Transactional for Craft CMS</h1>

This plugin provides a [Spotler Transactional](https://spotler.com/sendpro) integration for [Craft CMS](https://craftcms.com/).

## Requirements

This plugin requires Craft CMS 4.0.0+ or 5.0.0+.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Spotler Transactional”. Then click on the “Install” button in its modal window.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require perfectwebteam/craft-spotler-transactional

# tell Craft to install the plugin
./craft plugin/install craft-spotler-transactional
```

## Setup

Once Spotler Transactional is installed:

1. Go to **Settings** → **Email**.
2. Make sure that the **System Email Address** is set to an email for which the domain is a verified [Sending Domain](https://mandrillapp.com/settings/sending-domains). 
3. Change the **Transport Type** setting to **Spotler Transactional**.
4. Enter your **Client ID** and **Client SECRET**.
5. Optionally set the **Subaccount** from the Spotler Transactional page.
6. Optionally set the **Template Slug** from the Spotler Transactional page.
7. Click **Save**.

> **Tip:** The Client ID, Client SECRET, Subaccount and Template Slug settings can be set using environment variables. See [Environmental Configuration](https://craftcms.com/docs/3.x/config/#environmental-configuration) in the Craft docs to learn more about that.

Brought to you by [Perfect Web Team](https://perfectwebteam.com)