##############################
# Installing the module      #
##############################
1. Use the "Add a module from my computer" button on the modules page or copy the productinfo folder to your sites modules directory
2. Go to the Modules tab in your Back Office and click Install on the module (found under Administration)
3. Done!

NOTE: The module will override the Product class. If you have overridden the Product class before you will have
      to merge the changes manually. When installing this module previous overrides of the Product class will
      be renamed to "Product.php.bak".
      
##############################
# Updating from PS 1.4 to 1.5#
##############################
After you have upgraded your shop to 1.5.x, upload the new module version using "Add a module from my computer", then press "Update".

##############################
# How to use in Front Office #
##############################
You can access all the extra information just as you access ordinary product information.

The extra product information is stored in an array called "extra" in the Product class. So
for example, to access a field named "delivery_time" in product.tpl you write it like this:

{$product->extra.delivery_time}

And in e.g. product-list.tpl you can access the same field like this:

{$product.extra.delivery_time}

When loading a Product object all the extra field information will be loaded in to the extra array in the class automatically,
just as all the ordinary information.