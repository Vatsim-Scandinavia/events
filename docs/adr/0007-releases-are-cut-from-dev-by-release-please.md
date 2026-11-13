# Releases are cut from dev by release-please, and master only follows them

Releasing used to mean opening a pull request from `dev` to `master`, tagging `master` by hand and writing the notes in a draft GitHub release. Merging that pull request rewrote `dev`'s commits, so the two branches drifted apart. CI then built the image a second time for the release.

`dev` is now the only branch work lands on. Every pull request is squashed into it, and its title, held to Conventional Commits by a check, becomes the commit. [release-please](https://github.com/googleapis/release-please) reads those commits and keeps a Release PR open on `dev` with the next version and its changelog entry. Merging that PR is the decision to release. CI's last job then tags the merge commit, publishes the GitHub release, retags the image CI already tested and published for that commit (`sha-…`) as `vX.Y.Z`, `vX.Y` and `vX`, and fast-forwards `master` to it. `master` is no longer something people merge into. It always points at the latest release.

## Considered Options

- **release-please on `master`, still fed by promotion pull requests.** Rejected: a squashed promotion leaves `master` with one commit and an empty changelog, so promotions would have to be fast-forwards, and each release's changelog commit would have to be copied back to `dev`.
- **Dropping `master`.** Rejected: it's the default branch people land on, and having it point at the latest release is useful to anyone reading the code that's running.
- **Keeping the release workflow on tag pushes.** Rejected: a tag pushed by release-please with the workflow token starts no other workflow, so that would need a personal token or a GitHub App. Running the release inside CI's own run needs neither, and that run already holds the tested image.
- **Rebuilding the image for each release.** Rejected: it's the same commit but not the same bytes the smoke tests ran against, and it takes longer.
- **release-please for release candidates too.** Rejected: its pre-release mode is a setting you switch on and off around each candidate. A candidate is still a tag pushed by hand, and only its own tag is published.

## Consequences

Pull request titles are part of the release now. A wrong type puts a change under the wrong heading, or leaves it out, and can bump the wrong part of the version. The changelog only lists features, bug fixes, performance changes and breaking changes, because it's written for the people running a hub.

A run on `dev` is never cancelled by a newer push, so a release can't be half-published. If `master` was committed to directly, fast-forwarding it fails and it has to be put back by hand. Nothing is ever forced.

The Release PR is opened with the workflow token, so no workflows run on it. A required check on `dev` would block it, so repository admins need to be able to bypass those checks to merge it. It only changes `CHANGELOG.md`, `version.txt` and the manifest, and its merge runs CI on `dev` like any other push before anything is released.

Release candidates can only be cut from commits whose CI run on `dev` has finished, since that run is what publishes their image.
