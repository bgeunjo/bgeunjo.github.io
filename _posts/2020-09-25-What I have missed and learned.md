---
layout: post
title:  "What I have missed and learned !"
date:   2020-09-25
categories: ["2020","etc"]
update: 2020-09-29
comment: true
tags: [web,etc]
---

dreamhack 문제를 풀다가 느낀 점이 있어서 남기려고 한다. + 내가 공부하면서 배운 점들을 여기에 계속 업데이트하려고 한다.



내가 삽질을 한 포인트가 두 군데 있는데, 둘 다 배운 게 있는 거 같아서 기분은 좋다.



### 🧰 삽질 point 1

입력받은 변수로 명령어와 명령어의 인자들을 구성하는 문제였다. 여기서 type을 바꿔 `try~catch`문에서 일부러 `catch`문으로 흐름을 이동시킨 다음, 명령어를 실행시키려고 했다. 아이디어는 좋았지만 처음에는 어떻게 명령어와 인자들을 각각 넣어줘야 할 지 몰라서 시간을 보냈다.

결과적으로 명령어는 공백이 없는 문자열, 인자는 배열 or `undefined`여야 했는데, 인자를 배열로 주기 위해 다음과 같이 보냈다:

```
?q[0]=command&q[1][0]=arg1&q[1][1]=arg2
```

이제 원하는 명령어를 실행할 수 있는 상태다.

❗ **배운 점**: type 바꿔가면서 우회하는 법을 직접 써봤고 한발 더 나아가서 이차원 배열까지 써가면서 get 변수를 줄 수 있다.

### 🧰 삽질 point 2

원하는 명령어는 실행할 수 있지만 무슨 명령어로, 어떻게 flag를 읽어야 하는 지 몰랐다. reverse shell, webshell, file read 등등 시도해봤지만 다 안됐다. 

저렇게 한 이유가 처음에는 내가 `req.session`정보를 바꿀 수 없다고 생각했다. 분명히 `get`으로 session에 대한 정보를 봤는데도 그랬다. 왜냐면 !!!!!! 내가 로그인을 안하고 session 정보를 봤기 때문이다..😥



여기서도 배운 점? 이라 하긴 좀 애매한데 다음부터 로그인을 할 수 있으면 로그인을 하고! 문제를 풀어야겠다.

로그인하고 session 다시 보니 userid가 고스란히 담겨 있어서 명령어 실행해서 바꿀 수 있었다..



❗ **배운 점**: 로그인을 하고.. 뭐라도 해보자.



❗ **전체적으로 배운 점!** : 로컬에서 하나하나 테스트해보면서 하는 게 좋은 것 같다. 몇일 손으로 끄적이는 거 보다 1시간 로컬에서 하는 게 더 효과가 있었다.

---

❗ sqlite3에서 `select` 키워드가 필터링되어 있으면 `union value()`로 바꿔서 쓸 수 있다 ! 첨에 왜인진 모르겠지만 `insert into`를 해주니까 오류가 나서 저렇게 해서 풀었다 🤔 딱히 필터링이 많이 돼있는 건 아니라서 그렇게 어렵진 않았다!

---

### CCE 2020 - What I have missed and learned ! 

CCE 2020 하면서 배운건데 문제에서 `$_GET['idx']`를 받고, 그 값을 검증할 때는 `$_REQUEST['idx']`로 했다. 취약점은 `$_GET`이 아니라 `$_REQUEST`를 검증하기 때문에 발생하는데, 대회가 끝나고 친구가 링크를 보내줘서 봤더니 무슨 말인지 알 거 같았다.

> 🚀 [https://www.php.net/manual/en/ini.core.php#ini.variables-order](https://www.php.net/manual/en/ini.core.php#ini.variables-order){:target="_blank"}

`php.ini`파일에서 설정을 해줄 때 Data를 어떻게 다룰지에 대해 설정하는 부분이 있다. 거기서 **variables_order**와 **request_order**가 있는데, 각각은 다음과 같다:

- **variables_order** : Sets the order of the EGPCS (`E`nvironment, `G`et, `P`ost, `C`ookie, and `S`erver) variable parsing.

- **request_order** : This directive describes the order in which PHP registers GET, POST and Cookie variables into the `_REQUEST` array. Registration is done from left to right, newer values override older values. If this directive is not set,`variables_order` is used for `$_REQUEST`contents.

즉 검증을 할 때 `GET`과 `POST`로 둘다 보내버리면 검증은 `POST`로 하게 되는 것이다. 그래서 `GET`에서는 sqli가 가능하다!



❗ **배운 점** : 처음에 문제를 보고 플래그를 받아올 수 있는 방법들을 나열해봤을 때 저 방법도 당연히 있었다. 근데 `$_REQUEST['idx']`로 검증하니까 힘들겠네.. 하고 넘어갔었다. 입력하는 값과 검증하는 값이 정확히 뭔지 꼼꼼히 확인해봐야겠다 !



**ngrok**

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

그럼 `http://176297bf5049.ngrok.io `로 접속하면 내 로컬서버에 접근이 가능하다. 이걸 이용해서 문제풀 때나 로컬 서버를 테스트하고 싶을 때 이용하면 엄청 편할 거 같다! 😁

![image](https://user-images.githubusercontent.com/51329156/94522783-24f93480-026b-11eb-8fc1-bd05e22fcf00.png)

❗ **대회하고 나서 느낀 점** : 아직 하루종일 대회하는 거에 익숙하지 않아 중간에 집중력이 떨어지는 느낌을 많이 받았다. 코드를 보고 있는데 눈에도 안 들어오고 그랬다. 그래서 좀 오래 집중하는 연습을 해야겠다. 그리고 본선을 가면 배울 점이 많을 거라고 친구가 말해줬다. 좋은 기회를 받은 거 같아 좋았다 ! 수상을 목표로 열심히 해야겠다.👍

---

그리고 이건 그냥 깨알팁이었는데 나는 로컬에서 Markdown파일을 쓸 때 이미지를 캡쳐하고 복사해서 쓰기 때문에 github에 올리면 이미지가 깨지는 현상을 자주 겪었다. 그렇지 않고 github 블로그에 글을 올리려면 이미지를 하나하나 저장해놓고 commit을 해줘야 했다. 이게 너무 귀찮았는데 친구가 또 깨알팁을 알려줬다.

1. 자신의 아무 github 레포에 들어간다.
2. `Issues`에 들어간다.
3. `New issue`를 누르고 복사한 이미지 주소를 내용부분에 붙여넣기 한다.

이렇게 하면 github에서 Markdown 형식으로 리모트에서도 쓸 수 있는 주소를 준다. 이 주소를 이용해 이미지를 넣으면 이미지 깨짐 없이 Markdown을 사용할 수 있다! 개꿀 ! 😍



