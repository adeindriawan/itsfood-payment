ITS Food Payment API Documentation

Endpoint:
```sh
POST /payment/multiple-orders
```

Request body:
```sh
{
  orders: order1,order2,order3,etc,
  amount: (total gross amount of orders above),
  user_id: (user ID),
  user_name: (user name),
  user_email: (user email),
  user_phone: (user phone)
}
```
