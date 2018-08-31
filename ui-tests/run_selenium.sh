/usr/bin/xvfb-run Xvfb :2 -screen 0 1024x768x16 &
DISPLAY=:2 java -Dwebdriver.chrome.driver="/home/ilyatregubov/chromedriver" -jar selenium-server-standalone-3.13.0.jar
