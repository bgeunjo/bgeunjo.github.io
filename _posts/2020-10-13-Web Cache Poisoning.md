---
layout: post
title:  "Web cache poisoning"
date:   2020-10-14
categories: ["2020","web hacking"]
update:   2020-10-14
comment: true
tags: [web]
---

# Web cache poisoning

문제 풀면서 처음 접한 개념인데 한글로 정리된 글이 별로 없는 것 같아 Portswigger의 글을 정리해봤다.

> 🚀 [https://portswigger.net/web-security/web-cache-poisoning](https://portswigger.net/web-security/web-cache-poisoning)

## What is web cache poisoning?

**Web cache poisoning** 공격은 웹 서버와 캐쉬의 동작을 조작하여 위험한 HTTP 응답을 다른 유저에게 보낼 수 있는 공격 방법이다.

**Web cache poisoning** 공격은 두 단계로 진행된다.

1. 백엔드 서버로부터 위험한 페이로드를 포함하고 있는 응답을 유도해낼 수 있어야 한다.
2. 1단계가 성공하면, 그 응답이 **cached**되고 후에 다른 사용자에게 전달되어야 한다.

이 공격이 성공하면 XSS, Javascript injection, open redirection 등의 공격을 성공시킬 수도 있다.

### How does a web cache work?

**web cache**가 동작하는 방식을 이해해보자.

서버가 매 HTTP 요청마다 새로운 응답을 보내야 한다면, 서버에 과부화와 지연을 일으키고 사용자의 편의도 떨어진다. **web cache**는 이런 문제를 해결하기 위한 방법이다.

**web cache**는 사용자와 서버 사이에서 특정 요청에 대한 응답을 일정 기간동안 저장한다. 후에 다른 사용자가 똑같은 요청을 보내면, 백엔드와 interaction 없이 **cache**에 저장된 응답의 복사본을 사용자에게 전달한다. 이렇게 하면 중복되는 여러 요청을 쉽게 처리할 수 있다.

![](https://portswigger.net/web-security/images/caching.svg)

#### Cache keys

캐쉬가 HTTP 요청을 받으면, 캐쉬에 저장된 전달할 수 있는 응답이 있는지 보고, 아니면 요청을 백엔드서버로 포워딩한다. 캐쉬는 요청에서 미리 정의된 부분(**cache key**)을  이용해 같은 요청인지 아닌지 구분한다.  주로 request line과 `Host` 헤더를 포함한다. 요청 중에 cache key에 포함되지 않은 부분은 **unkeyed**라고 부른다.

새로 들어온 요청의 cache key가 이전 요청의 key와 같으면,  캐쉬는 두 요청이 같다고 여긴다. 그래서 캐쉬에 저장된 응답을 복사해서 전달한다. 요청의 다른 부분은 무시된다.

## Constructing a web cache poisoning attack

기본적인 **web cache poisoning** 공격은 다음 단계들을 포함한다:

1. **unkeyed input** 확인
2. 백엔드 서버로부터 원하는 응답 유도
3. 캐쉬된 응답 획득

### Identify and evaluate unkeyed inputs

모든 **web cache poisoning** 공격은 unkeyed input(such as headers)을 어떻게 조작하는지에 달렸다. 캐쉬된 응답을 전달할지 결정할 때 unkeyed input은 무시한다. 이 말은, 페이로드를 삽입해서 같은 cache key를 가진 요청에게 **poisoned** 응답을 유도할 수 있다는 뜻이다.

응답에 랜덤한 입력값을 넣어보면서 응답에 영향을 미치는지 안 미치는지 확인하면서 unkeyed inputs를 식별할 수 있다. **Burp Comparer**을 이용하면 두 응답간에 차이점을 확인할 수 있지만, 너무 많은 작업을 필요로 한다.

#### Param Miner

**Burp**의 [Param Miner](https://portswigger.net/bappstore/17d2949a985c4b7ca092728dba871943) 확장 프로그램을 사용해서 unkeyed input을 식별하는 과정을 자동화할 수 있다.

- 다운받고 요청 우클릭 ➜ **Guess headers** 클릭

만약 입력에 따른 차이가 응답에 나타나면, 로그를 남긴다.

Ex)

![](https://portswigger.net/web-security/images/param-miner.png)

**주의** : 실제 웹사이트에서 사용할 때는 **cache buster**를 켜서 실제 다른 사용자에게 영향을 미치지 않도록 하자.

### Elicit a harmful response from the back-end server

unkeyed input을 찾았으면, 웹사이트가 어떻게 그 input을 사용하는지 확인해야 한다. 이 과정이 제일 중요하다. 이 input이 필터링없이 응답에 포함되거나, 다른 데이터를 만드는데 사용된다면, **web cache poisoning**의 잠재적인 entry point다.

#### Get the response cached

의도한 응답을 이끌어내기 위해 input을 조작하는 것은 공격의 거의 절반을 차지하지만, 만약 응답을 캐쉬에 저장할 수 없으면 딱히 도움이 안될 수도 있다.

캐쉬되는지 안되는지는 file extension, content type, route, status code 그리고 응답 헤더 영향처럼 모든 요소에 영향을 받는다. 그래서 다른 페이지들에서 여러 요청들을 보내보고, cache가 어떻게 동작하는지 확인해봐야 한다. 악성 input을 포함하고 있는 응답을 캐쉬에 저장할 수만 있으면, 공격을 수행할 준비가 끝난 것이다.

![](https://portswigger.net/web-security/images/cache-poisoning.svg)

## Exploiting web cache poisoning vulnerabilities

> 🚀 [Exploiting cache design flaws](https://portswigger.net/web-security/web-cache-poisoning/exploiting-design-flaws)
>
> 🚀 [Exploiting cache implementation flaws](https://portswigger.net/web-security/web-cache-poisoning/exploiting-implementation-flaws)

위 두 페이지에 시나리오를 연습할만한 Lab들이 있다.