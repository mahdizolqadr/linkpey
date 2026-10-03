# WordPress.org Submission Guide — LinkPey

## Before you submit

1. Create a WordPress.org account: https://login.wordpress.org/register
2. WordPress.org username: `sokotebipayan` (must match `Contributors:` in readme.txt)
3. Prepare 3 screenshots (1200x900 recommended):
   - `screenshot-1.png` — Settings page
   - `screenshot-2.png` — Post metabox
   - `screenshot-3.png` — Send results
4. Prepare plugin directory assets (upload later to SVN `/assets/`):
   - `icon-256x256.png`
   - `banner-772x250.png`
   - `banner-1544x500.png` (optional, retina)

## Submit the plugin

1. Go to: https://wordpress.org/plugins/developers/add/
2. Upload a ZIP of the plugin folder (slug must be `linkpey`)
3. Paste the **Additional Information** text below

## Additional Information (for reviewers)

```
Plugin purpose

LinkPey connects WordPress to the LinkPey social publishing service (https://linkpey.com). Site owners can send post content from the WordPress editor to their own social channels (Telegram, Eitaa, Soroush, Rubika, Bale, Igap) that they have already connected and enabled in their LinkPey account.

This plugin is not standalone. It requires:
1. A LinkPey account
2. An API token generated from the LinkPey panel (Developer section)
3. At least one channel enabled for public API access in LinkPey

How to test

1. Install and activate the plugin
2. Go to Settings → LinkPey Settings
3. Enter a valid LinkPey API token
4. Click Test Connection
5. Edit or create a post
6. Use the Share to Social Networks metabox:
   - Select content options
   - Refresh channels
   - Select one or more channels
   - Preview message
   - Send

If reviewers need a test account/token, contact: Mahdi Zolqadr — mahdi.zolqadr@gmail.com

External service & data sent

The plugin communicates only with the LinkPey Public API (default: https://linkpey.com/api/public/).

Data sent to LinkPey when publishing:
- Post text built from selected post fields
- Selected channel identifiers (platform, chat_id)
- Optional images from the WordPress Media Library as base64-encoded files

Data not sent:
- WordPress user passwords
- Unrelated site content
- Arbitrary remote URLs for server-side fetching

Security notes

- API token encrypted at rest (AES-256-CBC, keys from AUTH_KEY + SECURE_AUTH_KEY)
- Token never exposed to frontend JavaScript
- AJAX uses nonces and capability checks
- Images read only from local wp-content/uploads attachments
- No SSRF: images sent as base64, not remote URLs

Admin UI is primarily Persian (RTL).
```

## Create the ZIP

From the parent directory of the plugin:

```bash
cd /path/to
zip -r linkpey.zip linkpey-wordpress -x "*.git*" -x "*WORDPRESS-ORG-SUBMISSION.md"
```

Rename the folder inside the ZIP to `linkpey` if needed:

```bash
cp -r linkpey-wordpress linkpey
zip -r linkpey.zip linkpey -x "*.git*" -x "linkpey/WORDPRESS-ORG-SUBMISSION.md"
rm -rf linkpey
```

## After approval

WordPress.org will give you an SVN repository:

```
https://plugins.svn.wordpress.org/linkpey/
```

Structure:

```
/trunk/          → plugin code
/tags/1.0.4/     → release tag
/assets/         → banners and icons only
```

## Common rejection reasons to avoid

- Missing readme.txt — included
- Undocumented external service — documented in readme
- Missing GPL license — included
- No uninstall cleanup — included
- Direct file access — index.php in all directories
- Missing privacy disclosure — wp_add_privacy_policy_content included
