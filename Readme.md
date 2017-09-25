# Voyager to Wordpress Importer Hook

To install the hook, simply visit your admin and go to `Settings->Hooks`, then click on `Add New` and type in `wordpress-import`, and click install.

Next visit: `/admin/wordpress-import` and you will see a screen that looks like the following:

![Wordpress import hook](https://i.imgur.com/CXeTzRL.png)

Simply, click to choose your Wordpress XML file, select a few options and click **Import**

And now you will have your Wordpress Posts, Pages, Categories, and Users migrated over to your database.

---

## Troubleshooting

Before doing your import you may also wish to change the `body` column in your post table from a type of `text` to be `longtext` it just depends on the length of some of your articles. In some imports this can cause an issue if it has not been changed.