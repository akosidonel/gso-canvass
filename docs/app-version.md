# Automatic release versioning

The sidebar and sign-in page read the release number from the root `VERSION` file
through `config('app.version')`. Do not edit `config/app.php` for each release.
The npm package version belongs to the original template and stays independent.

Every `npm run build` automatically stamps `VERSION` from the highest stable
`vMAJOR.MINOR.PATCH` tag reachable from the checked-out commit. Ordinary commits
keep that release number. Without release tags, the existing VERSION is retained.
A source distribution without Git metadata retains its packaged VERSION.

## Make a release

First commit and push the intended application changes, including this workflow.
Tag that committed release and push its tag:

```sh
git tag -a v1.0.1 -m "Release 1.0.1"
git push origin v1.0.1
```

Use patch increments for fixes, minor increments for compatible features, and major
increments for breaking changes. Tags must use the exact stable format above.
A tag does not capture uncommitted changes.

GitHub's **Build tagged release** workflow validates that the tag points to its
checkout, stamps the exact version, tests version resolution, builds the assets,
and uploads an artifact containing VERSION and public/build. It does not deploy
or create another commit. Deploy the source at that tag together with its version
file and assets. The version changes in the running app when that release is deployed.

For a local release checkout:

```sh
git fetch --tags
git checkout v1.0.1
npm run build
php artisan config:clear
```

If configuration caching is enabled on deployment, regenerate it after installing
the release VERSION file with `php artisan config:cache`. Restart long-running
application processes as part of deployment. If you only need to refresh the local
version file without rebuilding assets, run `npm run version:sync`.
