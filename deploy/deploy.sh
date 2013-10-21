COMMIT_HASH=`git show --quiet | head -n1`
VERSION=`python -c "print '$COMMIT_HASH'.split()[-1][:8]"`
BUCKET="wakari-static"
APP_NAME="elFinder"
PATH_PREFIX="apps/$APP_NAME"

s3cmd -c .s3cfg sync --recursive static/ s3://$BUCKET/$PATH_PREFIX/$VERSION/

URL="https://d3uybqv7a64u59.cloudfront.net/$PATH_PREFIX/$VERSION"
echo "CDN URL $URL"

echo $URL > url.txt
