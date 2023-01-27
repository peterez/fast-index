Guideline
1- What means 200, 429 and 401 codes?
Code 200; It's means working and ready
Code 429; Too many requests are thrown and when it's 24 hours it starts again
Code 401; It means that the service account you have installed is not authorized or authorized as "owner" in your webmaster tools account

Note : If you see 200 or 429 don't do anything. If you see 401 or 4xx codes, check your webmaster tools owners


2- Settings
Plugin Status: If you don't use set as passive
Post Types: Define the when you make post action which one post types will send to google. If you add new post type or added from plugin it will be shown in here
Daily Old Content Post: If you wanna sent to google your old posts type the your daily limit. Every service account has daily 200 limit and you have to split your limits daily new post and old posts
Post Status: It's means which post status trigger the this plugin


3- Is it legal?
Totally is legal. It's google service and working with google API. If you upload too much service account it's can be defined a spam. Just watch out for this


4- How work wordpress Cron Job ( Daily Old Content Post )?
The task list is triggered when someone logs into the site at or after the specified hours. These tasks will never be triggered if no one accesses your site. If no one visits your site during the day, log in to your site for once and the task list will be triggered automatically.


4- Mass Service Account creating and upload
Not: 1 Google account can create minimum 12 google cloud projects. Every google cloud project can enable Indexing API Service and every services has 200 daily limit. It's means you can send 2400 url to google. If you do same steps with your another google account you will get more 2400 limit and you 4800 url to google daily.

Step 1: Go Link https://console.cloud.google.com/
Step 2 : Create Project and Select
Step 3 : Create Service Account and make authorized you created email on service account
Step 4 : Add as owner on your webmaster tools
Step 5 : Go your wordpress admin dashboard and open Fast Index settings page and upload your service account JSON
Watch Video : https://youtu.be/RsJA66b5884