#!/bin/bash
set -e

# Can be all 3 kinds of releases
patch=0
if [[ "x$1" == "x--patch" ]] ; then
    patch=1
elif [[ "x$1" == "x--major" ]] ; then
    patch=2
fi

# stage all changes
git add -A .

# config auto stash
git config pull.rebase true
git config rebase.autoStash true

# pull all changes
git pull

# get workdir
wd=$(pwd)

# check for conflict
status=$(git ls-files --unmerged)
if [[ -z "$status" ]] ; then
    # update main if we are not patching
    if [[ "x$patch" != "x1" ]] ; then
        git checkout main && git pull && git checkout develop
    fi

    # get message
    read -e -p "Commit message: " msg

    # check if changes are there
    has=0
    out=$(git status --porcelain)
    if [[ ! -z "${out}" ]] ; then
        git stash --keep-index
        git stash save "for-release"
        if [[ ! $? -eq 0 ]]; then
            echo "ERROR: Stash changes failed"
            exit 2
        fi
        has=1
    fi

    # get a tag
    rtag=$(jq -r .version package.json)

    # checkout release branch
    if [[ "x$patch" != "x1" ]] ; then
        git flow release start "r$rtag"
    fi

    if [[ ${has} -eq 1 ]]; then
        git stash pop --index "stash@{0}"
        git stash drop
        git commit -m "DO: Automatic commit of changes"
        if [[ ! $? -eq 0 ]]; then
            echo "ERROR: Commit changes failed"
            exit 3
        fi
    fi

    # patch version
    if [[ "x$patch" == "x2" ]] ; then
        npm version --no-git-tag-version major -m "FINISH Major release %s"
    elif [[ "x$patch" == "x1" ]] ; then
        npm version --no-git-tag-version patch -m "FINISH Patch release %s"
    else
        npm version --no-git-tag-version minor -m "FINISH Minor release %s"
    fi

    # Rewrite the commit message
    git add .
    git commit --amend -m "$msg"
    tag=$(jq -r .version package.json)

    # Make sure the release tag is populated to child packages
    conf_file="composer.json"
    if [[ -f $conf_file ]] ; then
        echo "Patch $conf_file"
        jq ".version |= \"$tag\"" "$conf_file" > "$conf_file-new"
        rm "$conf_file"
        mv "$conf_file-new" "$conf_file"
    fi
    out=$(git status --porcelain)
    if [[ ! -z "${out}" ]] ; then
      git add .
      git commit --amend -m "$msg"
    fi

    # finish the release, but do not tag it
    if [[ "x$patch" != "x1" ]] ; then
        git flow release finish --notag -m "CHG: Merge the release $tag" "r$rtag"
        git checkout main
    fi


    # push regular
    if [[ "x$patch" != "x1" ]] ; then
        # move the tag
        git tag -a "v$tag" -m "$msg"
        git push origin develop
        git push origin main
    else
        # push data
        git push -f

        # move the tag
        git tag -a "v$tag" -m "$msg"
    fi
    git push --tags

    # go ack to develop
    if [[ "x$patch" != "x1" ]] ; then
        git checkout develop
    fi
else
    echo "ERROR: Repo is in conflict"
    exit 1
fi
