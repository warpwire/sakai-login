# Warpwire's Sakai Login Script

## First, gather the following necessary Information: ##
1. Web services: https://{SAKAI_URL}/direct/ 
     
     URL for web services. Again, the above URL format is standard in Sakai. Note that it may vary if your institution has customized it.

2. Sakai: {Sakai_URL}
     
     The URL of your Sakai instance. 

3. You will need to verify that Sakai REST APIs are active. 

4. You will need to make sure your firewall allows Warpwire to connect remotely to access your API services

     173.231.182.70
     
     173.231.182.71
     
     63.251.106.123
     
     63.251.106.126
     

## Options ##

There are two options for implementation. We have clients utilizing each of these options.

1. __Let us take care of it__. Provide the above information and we will host the script and login page allowing Sakai authentication outside of Sakai

     If you choose this option, Warpwire will handle the implementation
    
     If you choose this option, some user login data will pass through Warpwire servers
    
2. __Download the script from GitHub and host it yourself__. 

     If you choose this option, contact Warpwire to receive a key and secret
    
     If you choose this option, all user login data will remain on your servers
