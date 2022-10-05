# wp-temperatures
This is a plugin to monitor water boiler temperature data from remote sensors and plot them as a function of time.

## Installation
1. Upload to wordpress plugins
2. Use the shortcode: [temp_plot]
3. You must specify the query param **timeframe**.

#### Available options: 
- lastday: Returns last days data
- last2days: Returns last two days' data
- lastweek: Returns last week's data

E.x. page with permalink: **/temperatures?timeframe=lastday**
