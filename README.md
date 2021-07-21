# Reviews.co.uk for Magento

You'll need to sign up at [Reviews.co.uk](https://www.reviews.co.uk "Reviews.co.uk") or [Reviews.io](https://www.reviews.io "Reviews.io") to use this plugin.

# Installation

1. Via command line, `cd` to Magento2 root folder

2. As this plugin is hosted on [packagist.org](http://packagist.org), you simply use the following to instruct composer to fetch and install the module:

    ```bash
    composer require reviewscouk/reviews:0.0.38
    ```

3. When this is complete, `cd` to `/bin` and run the following:

    ```bash
    ./magento module:enable Reviewscouk_Reviews --clear-static-content
    ./magento setup:upgrade
    ```

## Basic Configuration

This extension requires a Store ID and an API key, obtained from your account area on [Reviews.co.uk](http://www.reviews.co.uk/) or [Reviews.io](http://www.reviews.io/). To find these details;

1. Log into your account, and navigate to `Company Setup -> Publish Reviews`
2. Then, select _Publishing API_
3. Here, you'll find your __Store ID__ and __API Key__ required by the module.

To configure the module, log into your Magento Administration panel (ensuring the module is correctly installed) and then;

1. Navigate to `Stores -> Configuration -> Reviews.co.uk -> Setup`
2. Select your __Region__. Reviews.co.uk customers will be _UK_, and Reviews.io customers will be _US_
3. Enter your __Store ID__ and __API KEY__

## Store Specific Integration

The Reviews Magento extension offers a number of features - which ones you use will depend on what you're looking to achieve. Below is in a brief outline of them all, and instruction on how to configure them.

### Review Collection Emails

Magento can automate the review collection process by sending customer/order data to the Reviews platform as soon as an order is completed. You can control the automated collection of Merchant and Product reviews independently by;

1. Navigate to  `Stores -> Configuration -> Reviews.co.uk -> Automation -> Review Collection`
2. To enable collection of Merchant Reviews set __Queue Merchant Review Emails__ to _Yes_
3. To enable collection of Product Reviews set __Queue Products Review Emails__ to _Yes_

### Product Feed

The product feed allows you to sync your product catalog with the Reviews platform. This feed will be available at _www.your-domain.com_/reviews/index/feed. To enable this;

1. Navigate to  `Stores -> Configuration -> Reviews.co.uk -> Automation -> Product Feed`
2. Under _Product Feed_, set __Product Feed__ to _Yes_.
3. When you save these settings, the URL of your feed will automatically be added to your Reviews account.

### Product Reviews Widget

Automatically pull through product specific reviews directly onto your product pages. To enable this;

1. Navigate to  `Stores -> Configuration -> Reviews.co.uk -> On Page Content -> Product Reviews Widget`
2. Set __Display Reviews Widget on Product Pages__ to _Yes_
3. You can set the colour used in the widget by entering a HEX colour code into the __Widget Hex Colour__ field. This code must be a valid 6 digit HEX value, with or without the leading # character.
4. This extension offers 2 types of widget, set via changing the value in the __Product Widget Version__ drop down. _Javascript Widget_ is the classic method of displaying reviews and will place an iFrame on the page with the reviews inside. The newer method, _Static Content Widget_, will display the reviews directly on the product page, rather than inside an iFrame. This is the preferred method as it allows search engines like Google to crawl and cache the content of the reviews.

### Rich Snippets

The Reviews.co.uk Magento modules allows you to automatically include structured Rich Snippet data on your pages. This data is used by search engines to better understand the content of your pages. Search engines also often use this data to improve the content of results on a Search Engine Result Page (SERP). You can find out more information [here](https://developers.google.com/search/docs/guides/intro-structured-data)

To enable Rich Snippets;

1. Navigate to  `Stores -> Configuration -> Reviews.co.uk -> On Page Content -> Rich Snippets`
2. To enable Rich Snippets containing Merchant review data, set __Enable Merchant Rich Snippets__ to _Yes_
3. To enable Rich Snippets containing Product review data, set __Enable Product Rich Snippets__ to _Yes_
