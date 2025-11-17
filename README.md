# Initialize a drupal11 recipe
```bash
mkdir my-drupalcms-app \
  && cd my-drupalcms-app \
  && lando init \
    --source cwd \
    --recipe drupal11 \
    --webroot web \
    --name my-drupalcms-app
```
# Start the environment
```bash
lando start
```

# Create latest Drupal CMS project via composer
```bash
lando composer create-project drupal/cms tmp && cp -r tmp/. . && rm -rf tmp
```

# Install drupal

```bash
lando drush site:install recipes/drupal_cms_starter --db-url=mysql://drupal11:drupal11@database/drupal11 -y
```

# List information about this app
```bash
lando info
```

URL: https://docs.lando.dev/plugins/drupal/getting-started.html