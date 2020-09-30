---
layout: post
title:  "ngrok ( for Windows )"
date:   2020-09-30
categories: ["2020","tips"]
update: 2020-09-30
tags: [tips]
---

### ngrok ( for Windows )

외부에서 내 로컬에 접속하게 할 때 평소에 되게 불편했는데 친구가 CTF할 때 필수라며 좋은 도구를 알려줬다! `ngrok`이라는 도군데 내 로컬 서버를 외부에서도  접속하게 해주는 터널 프로그램이다.

> 🚀 https://ngrok.com/download

다운로드 해주고 간단한 express 서버를 만든다.

**app.js**:

``` javascript
const express=require('express');
const app=express();
const PORT=1234;

app.get('/', (req,res)=>{
    res.send('simple_express');
})

app.listen(PORT,()=>{
    console.log(`Server is listening on ${PORT}`);
})
```

그리고 다운로드 받은 `ngrok.exe`를 이용해서 터널을 열어준다.(외부에서 접속 가능하게):

```
> ngrok.exe http 1234
```

그러면 다음과 같은 화면이 나온다.

![image](https://user-images.githubusercontent.com/51329156/94522610-dd72a880-026a-11eb-9b86-4c286a1c500c.png)

그럼 `http://176297bf5049.ngrok.io`로 접속하면 내 로컬서버에 접근이 가능하다. 이걸 이용해서 문제풀 때나 로컬 서버를 테스트하고 싶을 때 이용하면 엄청 편할 거 같다! 😁

![image](https://user-images.githubusercontent.com/51329156/94522783-24f93480-026b-11eb-8fc1-bd05e22fcf00.png)