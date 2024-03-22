// ==UserScript==
// @name         DHCP Utility
// @namespace    http://tampermonkey.net/
// @version      2024-03-22
// @description  try to take over the world!
// @author       Daniel Brooks
// @match        https://example.com/services_dhcp.php
// @icon         
// @grant        none
// ==/UserScript==

(function() {
    'use strict';

    const statTracker = {};
    const ipElements = []; // Array to store IPs and their corresponding elements
    const pfsenseUrl = 'https://example.com/ping.php'; // URL to endpoint

    // Function to pause execution for a given time
    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    let rows = document.querySelectorAll("table")[0].childNodes[3].childNodes;

    for (let i = 0; i < rows.length; i++) {
        if (rows[i].childNodes.length > 0) {
            let ipElement = rows[i].childNodes[7]; // Get the element that contains the IP
            let ip = ipElement.innerHTML.replaceAll("\t", "").replaceAll("\n", "");
            ipElements.push({ip, element: ipElement}); // Store both IP and element
            statTracker[ip] = 'unknown'; // Initialize each IP status as 'unknown'
        }
    }

    // Use an async function to await the sleep function
    (async () => {
        for (let {ip, element} of ipElements) {
            await fetch(`${pfsenseUrl}?ip=${encodeURIComponent(ip)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.alive) {
                        console.log(`${ip} is alive`);
                        element.style.backgroundColor = 'green'; // Change background color to green
                        statTracker[ip] = "alive";
                    } else {
                        console.log(`${ip} is not alive`);
                        element.style.backgroundColor = 'red'; // Change background color to red
                        statTracker[ip] = "dead";
                    }
                })
                .catch(error => {
                    console.error('Error checking IP:', ip, error);
                    element.style.backgroundColor = 'red'; // Assume dead if there's an error
                });
            await sleep(50); // Wait 50 mili-seconds before processing next request
        }
    })();
})();
