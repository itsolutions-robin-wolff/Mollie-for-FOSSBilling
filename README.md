# Mollie for FossBilling

#### Disclaimer
```Mollie is only for Componies. To get Verified at Mollie, you need a proof of your compony which is not older than one year.```

## Functionallity

With the "Mollie for Fosscord" module, you can process your customers' payments with the payment provider Mollie.
Mollie offers many different payment providers. Starting with credit cards, SEPA direct debit and ending with Paysafecard and voucher cards.

## Setup

1. Download the latest Release of the Module
2. Unzip the download
3. Upload the entire contents of the `upload` folder to the rootdirectory of your FOSSBilling instance
4. Go to the admin area of your FOSSBilling instance
5. Navigate to Payment Provider Management (System->Payment Gateways) and select 'New Payment Gateway'
6. Activate the Mollie module by pressing the gear on the right
7. Now enter your Mollie API keys

## How to get the Mollie-API-Keys

In order to get the Mollie API keys, you need to register with Mollie. If you already have an account and are already verified, you can skip steps 1 to 3.
1. Register with Mollie [here](https://my.mollie.com/dashboard/signup/16399288)
2. Now you have to give Mollie some information about yourself and your company.
3. Mollie will now check your data. This can take some time.
4. Now you can see the API keys in your Mollie dashboard under 'Developer->API Keys'. Please make sure to use the right ones, you have to enter them in the administration of your FOSSBilling instance
   (System->Payment Gateways->Mollie)