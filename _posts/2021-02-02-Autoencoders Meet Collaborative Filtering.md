---
layout: post
title:  "AutoRec 논문 리뷰, PyTorch로 구현하기"
date:   2021-02-02
categories: ["2021","ai"]
update:   2021-02-02
use_math: true
comment: true
tags: [ai,recommend]
---

## AutoRec: Autoencoders Meet Collaborative Filtering

### ABSTRACT

이 논문에서는 협업필터링(이하 CF)를 위한 autoencoder 프레임워크를 제시합니다. 저자의 경험에 따르면 **AutoRec**의 모델은 작고 효율적으로 학습이 가능하다고 Movielens 데이터셋과 Netflix 데이터셋에 대해 당시 state-of-the-art CF 기술이었던 biased MF, RBM-CF, LLORMA보다 월등한 성능을 냈다고 하네요.

### 👀 What is Autoencoder ?

인트로에서 설명했듯이 이 모델에서는 autoencoder를 사용합니다. 그렇다면 autoencoder가 대체 뭘까요?

>오토 인코더는 감독되지 않은 방식으로 효율적인 데이터 코딩을 학습하는 데 사용되는 인공 신경망의 한 유형입니다. 오토 인코더의 목적은 네트워크가 신호 "노이즈"를 무시하도록 훈련함으로써 일반적으로 차원 감소를 위한 데이터 셋에 대한 표현을 학습하는 것입니다.

*출처 : [위키피디아](https://en.wikipedia.org/wiki/Autoencoder)*

입력에서 출력으로 복사하는 신경망인데, 중간에 노이즈를 추가하거나 해서 단순 복사하지 못하도록 막고 이런 과정들을 통해서 데이터셋을 효율적으로 표현하는 방법을 학습합니다. 

![image](https://user-images.githubusercontent.com/51329156/106574235-00e19300-657e-11eb-8a99-c5192cd8cabd.png)

그림에서도 알 수 있듯이 오토인코더는 항상 인코더와 디코더, 두 부분으로 구성되어 있습니다.

- **인코더(encoder)** : 인지 네트워크(recognition network)라고도 하며, 입력을 내부 표현으로 변환합니다.

- **디코더(decoder)** : 생성 네트워크(generative nework)라고도 하며, 내부 표현을 출력으로 변환합니다.

입력과 출력층의 뉴런 수가 동일하다는 것만 제외하면 일반적인 MLP와 동일한 구조인 것 또한 알 수 있습니다. **Loss function**은 입력과 재구성된 입력값, 즉 출력의 차이를 가지고 계산합니다. 

정리해보면 오토인코더를 통해 입력값을 **재구성(reconstruction)**하고 입력 데이터에서 중요한 특성들을 학습하는 친구입니다. 오토인코더도 종류가 많은데, 후에 필요할 때 추가적으로 다루겠습니다.

**참고**

> 🚀 [오토인코더 (AutoEncoder)](https://excelsior-cjh.tistory.com/187)

### 2. THE AUTOREC MODEL

CF에서 m명의 사용자와, n개의 아이템에 대해 rating matrix
$$
R \in \mathbb{R}^{m \times n}
$$
를 가집니다. 각 User u는 item에 대한 평가 