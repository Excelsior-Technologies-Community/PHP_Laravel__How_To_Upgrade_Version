# PHP_Laravel__How_To_Upgrade_Version
# Step 1 : Install Laravel 11 and create new project 

 Composer Command
```php
composer create-project laravel/laravel PHP_Laravel__How_To_Upgrade_Version "^11.0"
```
# Step 2: Now open the project and select composer.json file and show the all laravel framework:

 <img width="1920" height="1080" alt="image" src="https://github.com/user-attachments/assets/9a8829f4-fb30-4f0b-88bb-699027075178" />


# Step 3 :Now upgrade version install to laravel 11 to laravel 12:
# Open the terminal and run this command:
```php
composer require laravel/framework:^12.0 --with-all-dependencies
```

# Explanation with Laravel Version Upgrade Example

# Assume your current project is using Laravel 7. Now you want to upgrade Laravel to a higher version like Laravel 10, 11, or 12.
```php
How version numbers work (^ symbol)
Version you write	What it means
^10.0	Install latest Laravel 10.x
^11.0	Install latest Laravel 11.x
^12.0	Install latest Laravel 12.x
```
So:
```php
Laravel 7 → 10

composer require laravel/framework:^10.0 --with-all-dependencies


Laravel 7 → 11

composer require laravel/framework:^11.0 --with-all-dependencies


Laravel 7 → 12

composer require laravel/framework:^12.0 --with-all-dependencies
```
# What does --with-all-dependencies mean?

# with-all-dependencies tells Composer to update not only Laravel, but also all packages that Laravel depends on, even if those packages are already installed with older versions.

# Step 4 : Then after successful install this command and show the composer.json file:
 
<img width="1665" height="985" alt="image" src="https://github.com/user-attachments/assets/3cef4578-611b-4188-a439-e67ce33d3f81" />
