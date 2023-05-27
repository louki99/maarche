#!/bin/bash

structures=$(echo $CI_COMMIT_REF_NAME | tr "/" "\n")

TRACKER=""

US=""

BRANCH=""

IT=1
for item in $structures
do
    if [ $IT = 1 ]; then
        TRACKER=$item
    fi

    if [ $IT = 2 ]; then
        US=$item
    fi

    if [ $IT = 3 ]; then
        BRANCH=$item
    fi

    IT=$((IT+1))
done


if [[ -z $TRACKER ]] || [[ -z $US ]]
then
    echo "Bad structure to find US ! => [TRACKER]/[US_ID]"
else

    echo $TRACKER
    echo $US

    if [[ $TRACKER == "feat" ]]; then
        TARGET_BRANCH="main";
      else
        if [[ $TRACKER == "fix" ]]; then
            TARGET_BRANCH="2301_releases";
        else
          echo "no feat or fix found :(";
          exit 0;
        fi;
    fi

    echo "GET https://forge.maarch.org/issues/$US.json"

    curl -H "X-Redmine-API-Key: ${REDMINE_API_KEY}" -H 'Content-Type: application/json' -X GET https://forge.maarch.org/issues/$US.json > issue_$US.json

    SUBJECT=`cat issue_$US.json | jq -r '.issue.subject'`

    MR_DESCRIPTION=$(awk 'BEGIN{RS="\n";ORS="\\n"}1' .gitlab/merge_request_templates/mr_template.md | sed -e "s/{US_ID}/$US/g" | sed -e "s/{US_TITLE}/$SUBJECT/g")

    BODY="{\"id\":\"$CI_PROJECT_ID\",\"source_branch\":\"$CI_COMMIT_REF_NAME\",\"target_branch\":\"$TARGET_BRANCH\",\"title\":\"Draft: [$US] $SUBJECT\",\"description\":\"$MR_DESCRIPTION\",\"remove_source_branch\":\"true\",\"squash\":\"false\"}"

    echo $BODY

    echo "POST https://labs.maarch.org/api/v4/projects/$CI_PROJECT_ID/merge_requests"

    curl -H "PRIVATE-TOKEN: $TOKEN_GITLAB" -H "Content-Type: application/json" -X POST -d "$BODY" "https://labs.maarch.org/api/v4/projects/$CI_PROJECT_ID/merge_requests"

    # Create comment on forge
    COMMIT_URL="$CI_PROJECT_URL/commit/$CI_COMMIT_SHA"
    NOTE="[**CREATION**] MR sur **$TARGET_BRANCH** (**$CI_COMMIT_REF_NAME**) par $CI_COMMIT_AUTHOR\n\n$COMMIT_URL"
    BODY="{\"issue\":{\"notes\":\"$NOTE\",\"private_notes\":false}}"
    curl -H 'Content-Type:application/json' -H "X-Redmine-API-Key:$REDMINE_API_KEY" -d "$BODY" -X PUT https://forge.maarch.org/issues/$US.json
fi
