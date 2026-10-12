<h1>TYPO3 Extension Gedankenfolger Sitepackage Min<br/>(gedankenfolger-sitepackage-min)</h1>
<p>
    Minimal sitepackage for TYPO3 14 projects. Provides base TypoScript, SCSS setup, Content Block wiring, and site-specific configuration.
</p>
<p>
    <a href="https://github.com/gf-ntiedt/gedankenfolger-sitepackage-min" target="_blank">GitHub</a> |
    <a href="https://www.gedankenfolger.de/" target="_blank">Gedankenfolger GmbH</a>
</p>

> **TYPO3 13 support** is maintained on the [`13.x`](../../tree/13.x) branch.

<h3>
    Contents of this file
</h3>
<ol>
    <li>
        <a href="#features">Features</a>
    </li>
    <li>
        <a href="#install">Install</a>
    </li>
    <li>
        <a href="#changelog">Changelog</a>
    </li>
    <li>
        <a href="#acknowledgements">Acknowledgements</a>
    </li>
    <li>
        <a href="#notes">Notes</a>
    </li>
    <li>
        <a href="#noticetrademark">Notice on Logo / Trademark Use</a>
    </li>
</ol>
<hr/>
<h3 id="features">
    Features
</h3>
<ul>
    <li>Base TypoScript via Site Set</li>
    <li>SCSS setup with Bootstrap integration (<code>ws_scss</code>)</li>
    <li>Content Block wiring and overrides</li>
    <li>Backend layouts and page TypoScript configuration</li>
    <li>ViewHelper namespace registration for <code>gfv:</code></li>
</ul>

<h3 id="install">
    Install
</h3>

<h4>1. Clone into packages/</h4>

```bash
git clone https://github.com/gf-ntiedt/gedankenfolger-sitepackage-min packages/gedankenfolger_sitepackage_min
```

<h4>2. Require via Composer</h4>

```bash
composer require gedankenfolger/gedankenfolger-sitepackage-min @dev
```

Or add to your project's `composer.json` repositories section:

```json
{
    "type": "path",
    "url": "packages/gedankenfolger_sitepackage_min"
}
```

<h4>3. Activate and configure</h4>

Activate the extension in the TYPO3 backend and include the Site Set in your site configuration.

<h3 id="changelog">
    Changelog
</h3>
<p>
    See <a href="CHANGELOG.md">CHANGELOG.md</a> — generated with <a href="https://git-cliff.org" target="_blank">git-cliff</a> from Conventional Commits.
</p>

<h3 id="acknowledgements">
    Acknowledgements
</h3>
<p>
    This extension builds on the following open source projects:
</p>
<ul>
    <li><a href="https://github.com/FriendsOfTYPO3/content-blocks" target="_blank">TYPO3 Content Blocks</a></li>
    <li><a href="https://getbootstrap.com/" target="_blank">Bootstrap Framework</a></li>
    <li><a href="https://github.com/WapplerSystems/ws_scss" target="_blank">SASS Compiler for TYPO3</a></li>
</ul>

<h3 id="notes">
    Notes
</h3>
<ul>
    <li>This sitepackage is not distributed via Packagist or TER — it is cloned directly into <code>packages/</code> for each project.</li>
    <li>Bootstrap assets are managed via Composer (<code>twbs/bootstrap</code>) and copied into <code>Resources/Public/</code> via <code>scripts/refresh-bootstrap-assets.sh</code>.</li>
    <li>Do not edit files under <code>Resources/Public/Scss/Bootstrap/</code> — they are overwritten by the refresh script.</li>
</ul>

<h3 id="noticetrademark">
    Notice on Logo / Trademark Use
</h3>
<p>
The logo used in this extension is protected by copyright and, where applicable, trademark law and remains the exclusive property of Gedankenfolger GmbH.

Use of the logo is only permitted in the form provided here. Any changes, modifications, or adaptations of the logo, as well as its use in other projects, applications, or contexts, require the prior written consent of Gedankenfolger GmbH.

In forks, derivatives, or further developments of this extension, the logo may only be used if explicit consent has been granted by Gedankenfolger GmbH. Otherwise, the logo must be removed or replaced with an own, non-protected logo.

All other logos and icons bundled with this extension are either subject to the TYPO3 licensing terms (The MIT License (MIT), see https://typo3.org) or are in the public domain.
</p>
<p>
For full license terms covering all graphic assets, see <a href="LICENSE-ICONS">LICENSE-ICONS</a>.
</p>
