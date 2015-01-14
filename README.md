**Cardstream Hosted Forms Module for Magento**

**Compatibility**

Compatible with Magento Version 1.9.1.0 and before. 

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
| Merchant ID | Enter your merchant ID here, or 100001 for test mode. |
| Merchant Shared Key | the pre sharted key set in your mms used to create payment signatures for each transaction |
| Country Code | The 3 digit ISO country code for your location. Use 826 for the UK. |
| Currency Code | The 3 digit ISO currency code. Use 826 for Pounds Sterling. |
| Payment in process | select the status to set while payment is being processed |
| New Order Status | Choose the status that will be applied to new orders. |
| Failed order status | select the status that will be applied to failed orders |
| Countries | You can choose to restrict payments from specific Countries. |

