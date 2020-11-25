---
layout: post
title:  "Preliminary of Recommendation"
date:   2020-11-25
categories: ["2020","AI"]
update: 2020-11-25
tags: [AI,Recommendation]
---

추천 알고리즘을 입문하려고 하는 저에게 친구가 보면 좋다고 소개해준 글을 정리해 보겠습니다.

### Intro 

![image](https://user-images.githubusercontent.com/51329156/100213332-d35ee480-2f51-11eb-9d0b-20b17ed189d8.png)

세상은 저희가 생각하는 것보다도 훨씬 더 밀접하게 연결되어 있습니다.  위 그림에서 확인할 수 있듯이 사용자와 사용자간의 연결이 있고, 사용자와 아이템간의 연결이 있습니다. `u1`과 `u2`는 sns 상 친구이고, 두 사용자는 둘다 i1(`Shape of You`)를 좋아합니다. 이 뿐만이 아니라 아이템과 아이템 사이에도 같은 가수, 같은 장르인지에 따라 연결이 만들어집니다. 

### Overview of Recommendation Engine

![image](https://user-images.githubusercontent.com/51329156/100213911-9515f500-2f52-11eb-8cb3-3dfe16edfec8.png)

**User interest**, 즉 사용자가 아이템에 매기는 점수는 별점을 주는 행위와 같이 명시적으로 드러날 수도 있지만, 실제로 그렇지 않은 경우도 있습니다. 이 때까지 사용자가 아이템과 상호작용한 기록들은 암묵적으로 사용자의 흥미를 나타낼 수 있습니다. 여기서 말하는 아이템은 영화, 친구, 뉴스 등등이 될 수 있습니다. 

여기서 문제는, **User**와 **Item**간에 존재하는 차이를 어떻게 해결하느냐 입니다. 당연하게도 사용자와 아이템은 다른 타입의 개체이기 때문에 사용자는 사용자의 특성에 의해 표현되고, 아이템은 아이템의 특성에 맞게 표현될 것입니다. 이러한 차이가 있음에도 불구하고, 아이템에 대한 사용자의 **User interest**를 구하는 게 주 목적이라 보시면 됩니다.

이 내용과 위 그림을  공식화해봅시다. **Input**은 이 때까지 사용자가 아이템들과 해온 상호작용의 기록들, 혹은 추가적인 사용자 정보같은 정보들이 될 수 있습니다. **Output**에는 주어진 타겟이나 사용자가 그 아이템에 대해 매길 점수(별점)의 예측값이 올 수 있습니다. 예를 들어 준용이가 테넷을 얼마나 좋아하는지 예측할 때, **Input**으로는 이 때까지 사용자가 다른 아이템들에 매긴 별점들을 넣고, **Output**에 준용이가 테넷에 매길 것 같은 별점을 내 놓는 것입니다. 위에서도 언급했듯이, 사용자와 아이템 사이에는 공통적인 특성이 없습니다.

### 협업 필터링(Collaborative Filtering)

**협업 필터링**은 **User interest**를 예측할 때 많은 사용자들로부터 모은 취향 정보들을 이용해 예측하는 기술을 의미합니다. 예를 들어서 준용이와 승윤이의 영화취향이 비슷할 때, 승윤이가 테넷을 좋아할 것인가? 에 대해서는 **YES**라고 판단합니다. (예제는 위와 이어집니다). 즉 비슷한 사용자들은 아마도 비슷한 취향을 가지고 있을 것이라고 예측합니다. 

**협업 필터링**의 종류에는 Memory-based CF, Model-based CF, Hybrid가 있습니다. 이 중 Memory-based CF와 Model-based CF에 대해서 알아보겠습니다.

#### Memory-based CF

![image](https://user-images.githubusercontent.com/51329156/100218541-381d3d80-2f58-11eb-8d71-438e9ed9112f.png)

위 그림에서 보면 u1는 i1에 대해 별점 5점을 남겼고, 다른 아이템들에 대해서는 별점을 남기지 않았습니다. 현실에서는 사용자들이 모든 아이템들에 별점을 남기지 않습니다. 앱을 처음 깔아서 사용하다보면 평점을 남겨달라는 문구가 뜨는데, 저 같은 경우는 거의 별점을 남겨본 기억이 없습니다 ㅋㅋ🤣 

여기서 저희가 해결해야 하는 문제가 빨간색 표시가 된 부분(u1의 i4에 대한 평가)을 예측하는 것이라고 해봅시다. **Memory-based CF**는 유사도를 기반하여 동작합니다. 이 때 말하는 유사도는 사용자와 사용자간의 유사도가 될 수도 있고, 아이템과 아이템간의 유사도가 될 수도 있습니다. 사용자 사이의 유사도를 기준으로 하는 경우는 **User-based**, 아이템 사이의 유사도를 기준으로 하는 경우는 **Item-based**이라고 합니다.

|      | 테넷 | 인터스텔라 | 겨울왕국 |
| ---- | ---- | ---------- | -------- |
| 준용 | 5    | 4          | ?        |
| 승윤 | ?    | 4          | 5        |
| 동욱 | 1    | ?          | 5        |
| 서연 | ?    | ?          | 3        |



> Reference
>
> 🚀 [https://next-nus.github.io/slides/tuto-cikm2019-public.pdf](https://next-nus.github.io/slides/tuto-cikm2019-public.pdf)
>
> 🚀 [협업 필터링 추천 시스템 (Collaborative Filtering Recommendation System)](https://scvgoe.github.io/2017-02-01-%ED%98%91%EC%97%85-%ED%95%84%ED%84%B0%EB%A7%81-%EC%B6%94%EC%B2%9C-%EC%8B%9C%EC%8A%A4%ED%85%9C-(Collaborative-Filtering-Recommendation-System)/)

 

