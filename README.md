**CharityClear Hosted Forms Module for Magento**

**Installation Instructions**

**Step 1:**

Copy the /app directory into your Magento directory. If you are asked if you
want to replace any existing files, click Yes.

**Step 2:**

Login to the Admin area of Magento. Click on System > Cache Management.
Click on the button labelled ‘Flush Magento Cache’, located at the top right
of the page.

![Magento Flush Cache](/images/magento-cache.png)

**Step 3:**

Click on System > Configuration then click on Payment Methods under the
Sales heading on the left-hand side of the page. All installed payment
methods will be displayed.

**Step 4:**

Click on the Cardstream Hosted Form bar to expand the module options.

![Magento Cardstream Config](/images/magento-cardstream-config.png)

| Config Option | Explanation |
| :-------------|:------------|
| Enabled | Choose ‘Yes’ to enable the Cardstream module. |
| Title   | This is the title of the payment module, as seen by your customers. |
| Merchant ID | Enter your merchant ID here, or 0000992 for test mode. |
| Payment Action | Choose whether to take funds immediately, or whether to pre-authorise payment. (N.B. Please note that if Pre-Auth is chosen, you will need to collect funds via the CharityClear Merchant Management System – MMS). Version 1.0.1 for Magento 1.4.1.1 |
| Country Code | The 3 digit ISO country code for your location. Use 826 for the UK. |
| Currency Code | The 3 digit ISO currency code. Use 826 for Pounds Sterling. |
| New Order Status | Choose the status that will be applied to new orders. |
| CallbackURL | This is the web address to which the response will be posted. If you have installed Magento in the root web folder, the URL will take the form: http://YOURDOMAIN/index.php/CharityclearHosted/standard/success/ If you have installed Magento in a sub-folder, the URL will take the form: http://YOURDOMAIN/SUB-FOLDER/index.php/CharityclearHosted/standard/success/ |
| Countries | You can choose to restrict payments from specific Countries. |

magento-direct-module
=====================

the code for magento can be found in the branches of this project.

for version Magento 1.7 and before see this link: https://github.com/CardStream/magento-direct-module/tree/Magento-1.7

for version Magento 1.8 and higher see this link: https://github.com/CardStream/magento-direct-module/tree/Magento-1.8
