THis extension allows you to auto-sync your public DNS provider (typically your domain registrars) from Plesk.

It helps simplify your management workload, and specifically makes the Let's Encrypt / SSL It and other extensions that modify Plesk DNS expecting the changes to go live immediately, work as designed.

The external DNS provider must have a REST API, and be implemented in this extension.
At this point in time, it requires a PHP module to be added to the extension.
Hoever, we may be able to allow parameterized configurations sometime in the future.

*This extension is **FREE** for acme_challenge (eg. Let's Encrypt) records ONLY.
For full functionality, pay me some amount - I'll figure out how much at some point.