echo "==> copy source into Apache directory"
rsync -rv --exclude=.git ../sampleshop-php /var/www/ecm-cli/ 
