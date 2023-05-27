#!/bin/bash

BRANCH=`echo $CI_COMMIT_MESSAGE | grep -oP "'(.*?)'" | head -1 | tr -d "'"`

ISSUE_ID=`echo $BRANCH | grep -oP "[0-9]*" | head -1`

IT=0

if [[ ! -z $ISSUE_ID ]]
then

    for row in $(curl --header "PRIVATE-TOKEN: $TOKEN_GITLAB" "https://labs.maarch.org/api/v4/projects/$CI_PROJECT_ID/merge_requests?state=merged&in=source_branch&search=$ISSUE_ID" | jq -r '.[] | @base64'); do
        _jq() {
        echo ${row} | base64 --decode | jq -r ${1}
        }

        if [ $IT = 0 ]; then
            URL=$(_jq '.web_url')
            AUTHOR=$(_jq '.merge_user.name')
            SOURCE_BRANCH=$(_jq '.source_branch')
            TARGET_BRANCH=$(_jq '.target_branch')

            NOTE="[**CLOTURE**] MR sur **$TARGET_BRANCH** (**$SOURCE_BRANCH**) par $AUTHOR\n\n$URL"
        fi

        IT=$((IT+1))
    done

    BODY="{\"issue\":{\"notes\":\"$NOTE\",\"private_notes\":false}}"
    
    echo $BODY
    
    curl -H 'Content-Type:application/json' -H "X-Redmine-API-Key:$REDMINE_API_KEY" -d "$BODY" -X PUT https://forge.maarch.org/issues/$ISSUE_ID.json
else
    echo "NO US FOUND !"
fi
