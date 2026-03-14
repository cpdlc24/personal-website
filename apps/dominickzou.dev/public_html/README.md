# CSE 135 Website

#### Team Members:
Just me :)

#### Link to Website: 

[dominickzou.dev](https://www.dominickzou.dev)

#### Autodeploy Setup: 
1. Push on main will trigger a GitHub actions workflow (`deploy.yml`)
2. This will login to the server as github-actions user
3. The user will then nagivate to the /var/www/dominickzou.dev/public_html/ directory.
4. The user will then perform `git pull origin` which will pull changes to the root directory for the website which would update the website. 
