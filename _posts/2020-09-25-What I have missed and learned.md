---
layout: post
title:  "What I have missed and learned !"
date:   2020-09-25
categories: ["2020","etc","tips"]
update: 2020-09-29
comment: true
tags: [web,etc,tips]
---

dreamhack 문제를 풀다가 느낀 점이 있어서 남기려고 한다. + 내가 공부하면서 배운 점들, 느낀 점들을 여기에 계속 업데이트하려고 한다.



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

저렇게 한 이유가 처음에는 내가 `req.session`정보를 바꿀 수 없다고 생각했기 때문이다. 분명히 `get`으로 session에 대한 정보를 봤는데도 그랬다. 왜냐면 !!!!!! 내가 로그인을 안하고 session 정보를 봤기 때문이다..😥



여기서도 배운 점? 이라 하긴 좀 애매한데 다음부터 로그인을 할 수 있으면 로그인을 하고! 문제를 풀어야겠다.

로그인하고 session 다시 보니 userid가 고스란히 담겨 있어서 명령어 실행해서 바꿀 수 있었다..



❗ **배운 점**: 로컬에서 하나하나 테스트해보면서 하는 게 좋은 것 같다. 몇일 손으로 끄적이는 거 보다 1시간 로컬에서 하는 게 더 효과가 있었다.

---

❗ sqlite3에서 `select` 키워드가 필터링되어 있으면 `union value()`로 바꿔서 쓸 수 있다 ! 첨에 왜인진 모르겠지만 `insert into`를 해주니까 오류가 나서 저렇게 해서 풀었다 🤔 딱히 필터링이 많이 돼있는 건 아니라서 그렇게 어렵진 않았다!

## CCE 2020 

CCE 2020 하면서 배운건데 문제에서 `$_GET['idx']`를 받고, 그 값을 검증할 때는 `$_REQUEST['idx']`로 했다. 취약점은 `$_GET`이 아니라 `$_REQUEST`를 검증하기 때문에 발생하는데, 대회가 끝나고 친구가 링크를 보내줘서 봤더니 무슨 말인지 알 거 같았다.

> 🚀 [https://www.php.net/manual/en/ini.core.php#ini.variables-order](https://www.php.net/manual/en/ini.core.php#ini.variables-order){:target="_blank"}

`php.ini`파일에서 설정을 해줄 때 Data를 어떻게 다룰지에 대해 설정하는 부분이 있다. 거기서 **variables_order**와 **request_order**가 있는데, 각각은 다음과 같다:

- **variables_order** : Sets the order of the EGPCS (`E`nvironment, `G`et, `P`ost, `C`ookie, and `S`erver) variable parsing.

- **request_order** : This directive describes the order in which PHP registers GET, POST and Cookie variables into the `_REQUEST` array. Registration is done from left to right, newer values override older values. If this directive is not set,`variables_order` is used for `$_REQUEST`contents.

즉 검증을 할 때 `GET`과 `POST`로 둘다 보내버리면 검증은 `POST`로 하게 되는 것이다. 그래서 `GET`에서는 sqli가 가능하다!



❗ **배운 점** : 처음에 문제를 보고 플래그를 받아올 수 있는 방법들을 나열해봤을 때 저 방법도 당연히 있었다. 근데 `$_REQUEST['idx']`로 검증하니까 힘들겠네.. 하고 넘어갔었다. 입력하는 값과 검증하는 값이 정확히 뭔지 꼼꼼히 확인해봐야겠다 !

❗ **대회하고 나서 느낀 점** : 아직 하루종일 대회하는 거에 익숙하지 않아 중간에 집중력이 떨어지는 느낌을 많이 받았다. 코드를 보고 있는데 눈에도 안 들어오고 그랬다. 그래서 좀 오래 집중하는 연습을 해야겠다. 그리고 본선을 가면 배울 점이 많을 거라고 친구가 말해줬다. 좋은 기회를 받은 거 같아 좋았다 ! 수상을 목표로 열심히 해야겠다.👍

## Dreamhack Web all solve 🎉

### 📗 Learnd point #1



dreamhack 마지막 문제를 풀면서 시간을 진짜 많이 보냈다. 다른 CTF에서도 많이 나왔던 FrontEnd와 API-Server(백엔드)가 분리된 웹 문제였다. API-Server가 분리되어 있어서 발생할 수 있는 여러 취약점들을 확인해봤는데, 잘 안 먹혔다ㅠ. 그래서 대체 취약점이 어디서 발생하는지 몰라서 시간을 오래 보냈다..

 FrontEnd에서 GET방식으로 받은 값을 API-Server에서 URL에서 path로 사용하는 걸 생각을 안하고 있었다.  내가 문제풀 때 부족했던 점:

```
- FrontEnd와 API-Server 사이에 동작을 제대로 이해를 못 했다.
- FrontEnd의 소스코드가 webpack을 이용해서 주어졌는데, 빨리 안 봤다.
- 머릿 속으로는 검증하는 부분 꼼꼼히 확인해야지 하면서 정규표현식이 허술한 걸 너무 늦게 알아챘다.
```

위의 문제점들을 고치면 :

```
- FrontEnd와 API-Server 사이에 동작을 이해하면 GET방식으로 입력한 값이 bot이 접근하는 URL의 path로 사용되는 것을 알 수 있다.
- 그러면 Path Traversal 공격을 할 수 있다.
- Path Traversal 공격을 할 때 ..이 막히는 걸 소스코드보고 우회할 수 있다.
```

내가 확인을 안 한게 아니고, 확인을 했는데 제대로 안 봐서 문제가 있는지도 몰랐던 거다. 좋은 경험이 된 거 같고 되게 느낀 점과 배운 점이 많은 문제였다.😂 

## 😎 Trivial tips


### Image into Markdown

로컬에서 Markdown파일을 쓸 때 이미지를 캡쳐하고 복사해서 쓰기 때문에 github에 올리면 이미지가 깨지는 현상을 자주 겪었다. 그렇지 않고 github 블로그에 글을 올리려면 이미지를 하나하나 저장해놓고 commit을 해줘야 했다. 이게 너무 귀찮았는데 친구가 또 깨알팁을 알려줬다.

1. 자신의 아무 github 레포에 들어간다.
2. `Issues`에 들어간다.
3. `New issue`를 누르고 복사한 이미지 주소를 내용부분에 붙여넣기 한다.

이렇게 하면 github에서 Markdown 형식으로 리모트에서도 쓸 수 있는 주소를 준다. 이 주소를 이용해 이미지를 넣으면 이미지 깨짐 없이 Markdown을 사용할 수 있다! 개꿀 ! 😆