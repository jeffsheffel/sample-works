#
#
# https://github.com/pmet/qcore
#
# NOTE: prerequisite to have image built before running (as this script does); refer to readme at github site.
#       docker build -t pmet/qc-admin-xdebug $(pwd)/docker/
#
# Create/Run qc-admin-xdebug Container
# IMPORTANT: Run this from the root of the repo or change $(pwd) to the path to the repo.

repoDir=${REPO_QC_ADMIN:-.}

if [ ! -d $repoDir ]; then
    echo ERROR: no such repository directory: $repoDir
    echo HINT: set/export REPO_QC_ADMIN to correct Git repository directory
    exit
fi

if [ ! -d $repoDir/docker ]; then
    echo ERROR: directory is not the expected repository, missing docker subdirectory: $repoDir
    echo HINT: set/export REPO_QC_ADMIN to correct Git repository directory
    exit
fi

if [ ! -f $repoDir/docker/localDev.env ]; then
    echo ERROR: missing docker environment file: $repoDir/docker/localDev.env
    exit
fi

echo "INFO: Initiating run of qc-admin linked to: $repoDir"

#    -p 3501:80 \
docker run -dt \
    --name="qc-admin-xdebug" \
    --env-file $repoDir/docker/localDev.env \
    --link mysql:mysql \
    --publish-all \
    -p 9000:9000 \
    -v $repoDir:/var/www/html/ \
    -e CUSTOMER_ID=99 \
    -e DEMO_SITE=0 \
    -e VIRTUAL_HOST=qc-admin-xdebug.docker \
    -e XDEBUG_CONFIG="remote-host=172.17.0.1" \
    -h qc-admin-xdebug.docker \
    pmet/qc-admin-xdebug

